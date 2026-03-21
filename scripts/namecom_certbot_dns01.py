#!/usr/bin/env python3
"""
Automatización DNS-01 con Name.com + Certbot para emitir SSL dinámico por tenant.

Flujo:
1) Ejecutar en modo "issue":
   - Llama a certbot con hooks manuales (auth/cleanup) apuntando a este mismo script.
2) Certbot ejecuta "auth-hook":
   - Lee CERTBOT_DOMAIN y CERTBOT_VALIDATION.
   - Crea TXT en Name.com: _acme-challenge.<subdominio>.<dominio>.
   - Espera propagación DNS (DoH + espera configurable).
3) Certbot valida y emite.
4) Certbot ejecuta "cleanup-hook":
   - Borra el TXT creado para dejar limpio el DNS.

Diseñado para llamarse desde Laravel/PHP o dentro de contenedor.
"""

import argparse
import base64
import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from typing import Dict, List, Optional

# =========================
# Configuración (editable)
# =========================
API_USER = os.getenv("API_USER", "TU_API_USER")
API_TOKEN = os.getenv("API_TOKEN", "TU_API_TOKEN")

DOMINIO_PRINCIPAL = os.getenv("DOMINIO_PRINCIPAL", "simsgrup2.app")
SUBDOMINIO = os.getenv("SUBDOMINIO", "hola")

# Certbot / rutas
CERTBOT_BIN = os.getenv("CERTBOT_BIN", "certbot")
CERTBOT_EMAIL = os.getenv("CERTBOT_EMAIL", "admin@simsgrup2.app")
CERTBOT_CONFIG_DIR = os.getenv("CERTBOT_CONFIG_DIR", "/etc/letsencrypt")
CERTBOT_WORK_DIR = os.getenv("CERTBOT_WORK_DIR", "/var/lib/letsencrypt")
CERTBOT_LOGS_DIR = os.getenv("CERTBOT_LOGS_DIR", "/var/log/letsencrypt")
CERTBOT_CERT_NAME = os.getenv("CERTBOT_CERT_NAME", "")  # opcional

# DNS propagation tuning
DNS_PROPAGATION_TIMEOUT = int(os.getenv("DNS_PROPAGATION_TIMEOUT", "180"))
DNS_PROPAGATION_INTERVAL = int(os.getenv("DNS_PROPAGATION_INTERVAL", "15"))
DNS_PROPAGATION_INITIAL_WAIT = int(os.getenv("DNS_PROPAGATION_INITIAL_WAIT", "20"))
DNS_TTL = int(os.getenv("DNS_TTL", "60"))

# Name.com API
NAMECOM_API_BASE = os.getenv("NAMECOM_API_BASE", "https://api.name.com/v4")


class ScriptError(Exception):
    """Error controlado del script."""



def log(msg: str) -> None:
    """Log simple a stdout."""
    print(f"[ssl-dns01] {msg}", flush=True)



def fail(msg: str, code: int = 1) -> None:
    """Salida con error controlado."""
    print(f"[ssl-dns01][ERROR] {msg}", file=sys.stderr, flush=True)
    sys.exit(code)



def _basic_auth_header(user: str, token: str) -> str:
    raw = f"{user}:{token}".encode("utf-8")
    return "Basic " + base64.b64encode(raw).decode("ascii")



def http_json(
    method: str,
    url: str,
    user: str,
    token: str,
    payload: Optional[Dict] = None,
    timeout: int = 30,
) -> Dict:
    """HTTP JSON helper para Name.com API."""
    data = None
    headers = {
        "Authorization": _basic_auth_header(user, token),
        "Accept": "application/json",
        "Content-Type": "application/json",
    }

    if payload is not None:
        data = json.dumps(payload).encode("utf-8")

    req = urllib.request.Request(url=url, data=data, method=method.upper(), headers=headers)

    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = resp.read().decode("utf-8")
            if not body:
                return {}
            return json.loads(body)
    except urllib.error.HTTPError as e:
        detail = ""
        try:
            detail = e.read().decode("utf-8")
        except Exception:
            detail = str(e)
        raise ScriptError(f"HTTP {e.code} en {method} {url}: {detail}") from e
    except urllib.error.URLError as e:
        raise ScriptError(f"Error de red en {method} {url}: {e}") from e



def build_fqdn(subdomain: str, root_domain: str) -> str:
    """Compone FQDN final para certificado."""
    subdomain = subdomain.strip().strip(".")
    root_domain = root_domain.strip().strip(".")
    if not subdomain:
        return root_domain
    return f"{subdomain}.{root_domain}"



def acme_host_relative(fqdn: str, root_domain: str) -> str:
    """
    Calcula el host relativo para Name.com:
    - fqdn: hola.simsgrup2.app
    - TXT fqdn: _acme-challenge.hola.simsgrup2.app
    - host relativo (Name.com): _acme-challenge.hola
    """
    fqdn = fqdn.strip(".")
    root_domain = root_domain.strip(".")
    suffix = "." + root_domain

    if fqdn == root_domain:
        return "_acme-challenge"

    if not fqdn.endswith(suffix):
        raise ScriptError(f"El FQDN '{fqdn}' no pertenece al dominio base '{root_domain}'")

    left = fqdn[: -len(suffix)]
    return f"_acme-challenge.{left}"



def create_txt_record(root_domain: str, host: str, value: str, ttl: int) -> Dict:
    """Crea un TXT en Name.com."""
    url = f"{NAMECOM_API_BASE}/domains/{urllib.parse.quote(root_domain)}/records"
    payload = {
        "host": host,
        "type": "TXT",
        "answer": value,
        "ttl": ttl,
    }
    return http_json("POST", url, API_USER, API_TOKEN, payload=payload)



def list_records(root_domain: str) -> List[Dict]:
    """Lista registros DNS del dominio en Name.com."""
    url = f"{NAMECOM_API_BASE}/domains/{urllib.parse.quote(root_domain)}/records"
    response = http_json("GET", url, API_USER, API_TOKEN)
    return response.get("records", [])



def delete_record(root_domain: str, record_id: int) -> None:
    """Elimina un registro DNS por ID."""
    url = f"{NAMECOM_API_BASE}/domains/{urllib.parse.quote(root_domain)}/records/{record_id}"
    http_json("DELETE", url, API_USER, API_TOKEN)



def delete_matching_txt_records(root_domain: str, host: str, value: str) -> int:
    """Elimina TXT(s) que coincidan con host y valor. Devuelve cantidad eliminada."""
    records = list_records(root_domain)
    deleted = 0
    for r in records:
        if r.get("type") == "TXT" and r.get("host") == host and r.get("answer") == value:
            rid = r.get("id")
            if rid is not None:
                delete_record(root_domain, int(rid))
                deleted += 1
    return deleted



def query_doh_txt_google(name: str) -> List[str]:
    """Consulta TXT por DoH Google."""
    url = "https://dns.google/resolve?" + urllib.parse.urlencode({"name": name, "type": "TXT"})
    req = urllib.request.Request(url, headers={"Accept": "application/dns-json"}, method="GET")
    with urllib.request.urlopen(req, timeout=20) as resp:
        data = json.loads(resp.read().decode("utf-8"))
    answers = data.get("Answer", []) or []
    values = []
    for a in answers:
        d = a.get("data", "")
        values.append(d.strip('"'))
    return values



def query_doh_txt_cloudflare(name: str) -> List[str]:
    """Consulta TXT por DoH Cloudflare."""
    url = "https://cloudflare-dns.com/dns-query?" + urllib.parse.urlencode({"name": name, "type": "TXT"})
    req = urllib.request.Request(
        url,
        headers={"Accept": "application/dns-json", "User-Agent": "python-ssl-dns01"},
        method="GET",
    )
    with urllib.request.urlopen(req, timeout=20) as resp:
        data = json.loads(resp.read().decode("utf-8"))
    answers = data.get("Answer", []) or []
    values = []
    for a in answers:
        d = a.get("data", "")
        values.append(d.strip('"'))
    return values



def wait_for_dns_propagation(txt_fqdn: str, expected_value: str, timeout: int, interval: int, initial_wait: int) -> None:
    """
    Espera propagación del TXT en resolvers públicos.
    Requiere verlo en Google DoH y Cloudflare DoH.
    """
    if initial_wait > 0:
        log(f"Espera inicial de {initial_wait}s para propagación DNS...")
        time.sleep(initial_wait)

    deadline = time.time() + timeout
    while time.time() < deadline:
        google_vals, cloudflare_vals = [], []
        try:
            google_vals = query_doh_txt_google(txt_fqdn)
        except Exception as e:
            log(f"Google DoH no disponible temporalmente: {e}")

        try:
            cloudflare_vals = query_doh_txt_cloudflare(txt_fqdn)
        except Exception as e:
            log(f"Cloudflare DoH no disponible temporalmente: {e}")

        ok_google = expected_value in google_vals
        ok_cf = expected_value in cloudflare_vals

        log(
            f"Propagación TXT {txt_fqdn} | "
            f"google={'OK' if ok_google else 'NO'} cloudflare={'OK' if ok_cf else 'NO'}"
        )

        if ok_google and ok_cf:
            log("Propagación DNS confirmada.")
            return

        time.sleep(interval)

    raise ScriptError(
        f"No propagó el TXT '{txt_fqdn}' con el valor esperado dentro de {timeout}s"
    )



def certbot_auth_hook(root_domain: str) -> None:
    """
    Hook de autenticación:
    - Certbot provee CERTBOT_DOMAIN y CERTBOT_VALIDATION
    - Crea TXT _acme-challenge.*
    - Espera propagación
    """
    certbot_domain = os.getenv("CERTBOT_DOMAIN", "").strip().strip(".")
    certbot_validation = os.getenv("CERTBOT_VALIDATION", "").strip()

    if not certbot_domain or not certbot_validation:
        raise ScriptError("Faltan variables CERTBOT_DOMAIN o CERTBOT_VALIDATION en auth-hook")

    host = acme_host_relative(certbot_domain, root_domain)
    txt_fqdn = f"{host}.{root_domain}".strip(".")

    log(f"Creando TXT en Name.com: host={host} domain={root_domain}")
    response = create_txt_record(root_domain, host, certbot_validation, DNS_TTL)
    record_id = response.get("id", "desconocido")
    log(f"TXT creado (id={record_id}) para challenge.")

    wait_for_dns_propagation(
        txt_fqdn=txt_fqdn,
        expected_value=certbot_validation,
        timeout=DNS_PROPAGATION_TIMEOUT,
        interval=DNS_PROPAGATION_INTERVAL,
        initial_wait=DNS_PROPAGATION_INITIAL_WAIT,
    )



def certbot_cleanup_hook(root_domain: str) -> None:
    """
    Hook de limpieza:
    - Certbot vuelve a pasar CERTBOT_DOMAIN y CERTBOT_VALIDATION
    - Elimina TXT correspondiente
    """
    certbot_domain = os.getenv("CERTBOT_DOMAIN", "").strip().strip(".")
    certbot_validation = os.getenv("CERTBOT_VALIDATION", "").strip()

    if not certbot_domain or not certbot_validation:
        raise ScriptError("Faltan variables CERTBOT_DOMAIN o CERTBOT_VALIDATION en cleanup-hook")

    host = acme_host_relative(certbot_domain, root_domain)
    deleted = delete_matching_txt_records(root_domain, host, certbot_validation)
    log(f"Limpieza DNS completada. TXT eliminados: {deleted}")



def issue_certificate(root_domain: str, subdomain: str) -> None:
    """
    Ejecuta certbot en modo manual automático con hooks.
    """
    fqdn = build_fqdn(subdomain, root_domain)
    script_path = os.path.abspath(__file__)

    cmd = [
        CERTBOT_BIN,
        "certonly",
        "--manual",
        "--preferred-challenges",
        "dns",
        "--manual-public-ip-logging-ok",
        "--manual-auth-hook",
        f"python3 {script_path} --mode auth-hook --domain {root_domain}",
        "--manual-cleanup-hook",
        f"python3 {script_path} --mode cleanup-hook --domain {root_domain}",
        "-d",
        fqdn,
        "--agree-tos",
        "--non-interactive",
        "--email",
        CERTBOT_EMAIL,
        "--config-dir",
        CERTBOT_CONFIG_DIR,
        "--work-dir",
        CERTBOT_WORK_DIR,
        "--logs-dir",
        CERTBOT_LOGS_DIR,
    ]

    if CERTBOT_CERT_NAME.strip():
        cmd.extend(["--cert-name", CERTBOT_CERT_NAME.strip()])

    log(f"Iniciando emisión SSL para: {fqdn}")
    log("Comando certbot: " + " ".join(cmd))

    result = subprocess.run(cmd, text=True)
    if result.returncode != 0:
        raise ScriptError(f"Certbot terminó con código {result.returncode}")

    cert_dir_name = CERTBOT_CERT_NAME.strip() if CERTBOT_CERT_NAME.strip() else fqdn
    live_dir = os.path.join(CERTBOT_CONFIG_DIR, "live", cert_dir_name)

    log("Certificado emitido correctamente.")
    log(f"Ruta esperada fullchain: {os.path.join(live_dir, 'fullchain.pem')}")
    log(f"Ruta esperada privkey: {os.path.join(live_dir, 'privkey.pem')}")



def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="SSL dinámico con Name.com + Certbot DNS-01")
    parser.add_argument(
        "--mode",
        choices=["issue", "auth-hook", "cleanup-hook"],
        default="issue",
        help="issue: emite cert; auth-hook/cleanup-hook: usados por certbot",
    )
    parser.add_argument(
        "--domain",
        default=DOMINIO_PRINCIPAL,
        help="Dominio principal (ej: simsgrup2.app)",
    )
    parser.add_argument(
        "--subdomain",
        default=SUBDOMINIO,
        help="Subdominio dinámico (ej: hola)",
    )
    return parser.parse_args()



def validate_required_config() -> None:
    if not API_USER or API_USER == "TU_API_USER":
        raise ScriptError("Configura API_USER")
    if not API_TOKEN or API_TOKEN == "TU_API_TOKEN":
        raise ScriptError("Configura API_TOKEN")
    if not CERTBOT_EMAIL:
        raise ScriptError("Configura CERTBOT_EMAIL")



def main() -> None:
    args = parse_args()

    try:
        validate_required_config()

        root_domain = args.domain.strip().strip(".")
        subdomain = args.subdomain.strip().strip(".")

        if args.mode == "issue":
            issue_certificate(root_domain, subdomain)
        elif args.mode == "auth-hook":
            certbot_auth_hook(root_domain)
        elif args.mode == "cleanup-hook":
            certbot_cleanup_hook(root_domain)
        else:
            raise ScriptError(f"Modo no soportado: {args.mode}")

    except ScriptError as e:
        fail(str(e), 1)
    except KeyboardInterrupt:
        fail("Interrumpido por usuario", 130)
    except Exception as e:
        fail(f"Error no controlado: {e}", 1)


if __name__ == "__main__":
    main()
