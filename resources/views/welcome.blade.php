<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIMS - Gestió de Flotes de Vehicles en Temps Real</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts / Styles -->
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: { sans: ['Inter', 'sans-serif'] },
                        colors: {
                            primary: { 50: '#f0fdfa', 100: '#ccfbf1', 500: '#14b8a6', 600: '#0d9488', 900: '#134e4a' },
                            secondary: { 500: '#6366f1', 600: '#4f46e5' }
                        },
                        animation: {
                            'fade-in-up': 'fadeInUp 0.8s ease-out forwards',
                            'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        },
                        keyframes: {
                            fadeInUp: {
                                '0%': { opacity: '0', transform: 'translateY(20px)' },
                                '100%': { opacity: '1', transform: 'translateY(0)' },
                            }
                        }
                    }
                }
            }
        </script>
        <style>
            .animate-on-scroll { opacity: 0; transform: translateY(20px); transition: all 0.8s ease-out; }
            .is-visible { opacity: 1; transform: translateY(0); }
            .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); }
        </style>
    @endif
    <!-- AlpineJS for interactivity -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased overflow-x-hidden selection:bg-primary-500 selection:text-white" x-data="{ scrolled: false }" @scroll.window="scrolled = (window.pageYOffset > 20)">

    <!-- Navbar -->
    <header :class="{'glass shadow-md': scrolled, 'bg-transparent': !scrolled}" class="fixed w-full top-0 z-50 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center gap-2 cursor-pointer">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-500 to-secondary-500 flex items-center justify-center text-white font-bold text-xl shadow-lg">S</div>
                    <span class="font-extrabold text-2xl tracking-tight text-slate-900">SIMS</span>
                </div>
                <div class="hidden md:flex space-x-8">
                    <a href="#funcionalitats" class="text-sm font-medium text-slate-600 hover:text-primary-600 transition-colors">Funcionalitats</a>
                    <a href="#beneficis" class="text-sm font-medium text-slate-600 hover:text-primary-600 transition-colors">Beneficis</a>
                    <a href="http://localhost:3000/login" class="text-sm font-medium text-slate-600 hover:text-primary-600 transition-colors">Contactan's</a>
                </div>
                <div class="flex items-center">
                    <a href="http://localhost:3000/register" class="text-sm font-semibold bg-primary-600 text-white px-5 py-2.5 rounded-full hover:bg-primary-700 hover:shadow-lg hover:shadow-primary-500/30 transition-all shadow-md transform hover:-translate-y-0.5">Nova Empresa</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
        <div class="absolute top-0 w-full h-full bg-slate-50 overflow-hidden -z-10">
            <div class="absolute -top-40 -right-40 w-96 h-96 bg-primary-200 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-pulse-slow"></div>
            <div class="absolute top-20 -left-20 w-72 h-72 bg-secondary-200 rounded-full mix-blend-multiply filter blur-3xl opacity-50 animate-pulse-slow" style="animation-delay: 1s;"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center animate-fade-in-up">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary-50 border border-primary-100 text-primary-600 text-sm font-semibold mb-6">
                <span class="flex h-2 w-2 relative">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-primary-500"></span>
                </span>
                Gestió en Temps Real
            </div>
            <h1 class="text-5xl md:text-7xl font-extrabold text-slate-900 tracking-tight mb-6 leading-tight">
                El control absolut de la <br class="hidden md:block"/>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-600 to-secondary-600">teva flota de vehicles</span>
            </h1>
            <p class="mt-4 text-xl text-slate-600 max-w-2xl mx-auto mb-10 leading-relaxed">
                El software que permet gestionar i monitoritzar vehicles en temps real. Amigable, multdispositiu i pensat per estalviar-te temps i diners.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <a href="http://localhost:3000/register" class="bg-slate-900 hover:bg-slate-800 text-white font-semibold text-lg px-8 py-4 rounded-full shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-1">Contactan's</a>
                <a href="#funcionalitats" class="bg-white hover:bg-slate-50 text-slate-700 font-semibold text-lg px-8 py-4 rounded-full border border-slate-200 shadow-sm hover:shadow-md transition-all">Explora SIMS</a>
            </div>
        </div>
    </section>

    <!-- Two Layers Section -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 observe-me animate-on-scroll">
                <h2 class="text-sm text-secondary-600 font-bold tracking-widest uppercase mb-2">Complet i Versàtil</h2>
                <h3 class="text-3xl md:text-4xl font-extrabold text-slate-900">Dues capes per una gestió 360º</h3>
            </div>
            
            <div class="grid md:grid-cols-2 gap-12">
                <div class="bg-slate-50 rounded-3xl p-10 border border-slate-100 shadow-sm hover:shadow-xl transition-shadow observe-me animate-on-scroll" style="transition-delay: 100ms;">
                    <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-600 rounded-2xl flex items-center justify-center text-white mb-6 shadow-lg shadow-primary-500/30">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h4 class="text-2xl font-bold text-slate-900 mb-4">Dashboard d'Administradors</h4>
                    <p class="text-slate-600 text-lg">Control total de la flota. Gestiona vehicles, aprova reserves, assigna permisos i revisa mètriques i estats de tickets des d'un panell de control complet i amigable.</p>
                </div>
                <div class="bg-slate-50 rounded-3xl p-10 border border-slate-100 shadow-sm hover:shadow-xl transition-shadow observe-me animate-on-scroll" style="transition-delay: 200ms;">
                    <div class="w-14 h-14 bg-gradient-to-br from-secondary-500 to-secondary-600 rounded-2xl flex items-center justify-center text-white mb-6 shadow-lg shadow-secondary-500/30">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <h4 class="text-2xl font-bold text-slate-900 mb-4">App pels Usuaris</h4>
                    <p class="text-slate-600 text-lg">La facilitat a la butxaca. App 100% responsive des d'on els usuaris poden reservar vehicles a l'instant, consultar l'estat i resoldre dubtes amb el nostre Chatbot dedicat.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="funcionalitats" class="py-24 bg-slate-900 text-white overflow-hidden relative">
        <div class="absolute -right-20 top-20 w-72 h-72 bg-primary-600 rounded-full mix-blend-screen filter blur-3xl opacity-20"></div>
        <div class="absolute -left-20 bottom-20 w-72 h-72 bg-secondary-600 rounded-full mix-blend-screen filter blur-3xl opacity-20"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16 observe-me animate-on-scroll">
                <h2 class="text-sm font-bold text-primary-400 tracking-widest uppercase mb-2">Tot en una eina</h2>
                <h3 class="text-3xl md:text-4xl font-extrabold">Funcionalitats</h3>
                <p class="mt-4 text-slate-400 max-w-2xl mx-auto text-lg">SIMS posseeix tot el que necessita la teva organització per un funcionament eficient.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Feature 1 -->
                <div class="bg-slate-800/50 backdrop-blur-md p-8 rounded-2xl border border-slate-700 hover:bg-slate-800 transition-colors observe-me animate-on-scroll" style="transition-delay: 100ms;">
                    <div class="w-12 h-12 bg-slate-700 text-primary-400 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Mapa Interactiu en Temps Real</h3>
                    <p class="text-slate-400">Coneix la ubicació exacta de tots els vehicles al moment a través d'un mapa dinàmic.</p>
                </div>
                <!-- Feature 2 -->
                <div class="bg-slate-800/50 backdrop-blur-md p-8 rounded-2xl border border-slate-700 hover:bg-slate-800 transition-colors observe-me animate-on-scroll" style="transition-delay: 200ms;">
                    <div class="w-12 h-12 bg-slate-700 text-secondary-400 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Sistema de Tickets</h3>
                    <p class="text-slate-400">Comunicació directa i eficaç entre usuaris i administradors per reportar incidències o suggeriments.</p>
                </div>
                <!-- Feature 3 -->
                <div class="bg-slate-800/50 backdrop-blur-md p-8 rounded-2xl border border-slate-700 hover:bg-slate-800 transition-colors observe-me animate-on-scroll" style="transition-delay: 300ms;">
                    <div class="w-12 h-12 bg-slate-700 text-blue-400 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Chatbot Intel·ligent</h3>
                    <p class="text-slate-400">Resolució de dubtes ràpida pels usuaris gràcies a un assistent virtual preparat per ajudar 24/7.</p>
                </div>
                <!-- Feature 4 -->
                <div class="bg-slate-800/50 backdrop-blur-md p-8 rounded-2xl border border-slate-700 hover:bg-slate-800 transition-colors observe-me animate-on-scroll" style="transition-delay: 400ms;">
                    <div class="w-12 h-12 bg-slate-700 text-purple-400 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">100% Responsive</h3>
                    <p class="text-slate-400">Disseny perfectament adaptat a qualsevol dispositiu: mòbil, tablet o ordinador de sobretaula.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Target Audience & Benefits Section -->
    <section id="beneficis" class="py-24 bg-slate-50 relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-[2.5rem] p-10 md:p-16 shadow-xl border border-slate-100 flex flex-col lg:flex-row items-center gap-16 observe-me animate-on-scroll">
                <div class="w-full lg:w-1/2">
                    <h2 class="text-4xl font-extrabold text-slate-900 mb-6 leading-tight">Optimitzem operacions per estalviar <span class="text-primary-600">temps i diners</span></h2>
                    <p class="text-lg text-slate-600 mb-8 leading-relaxed">
                        No importa si la teva organització és pública o privada. SIMS s'adapta a la teva organització, reduint el temps d'inactivitat dels vehicles i digitalitzant els processos de l'empresa de forma molt intuïtiva.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-xl font-semibold text-slate-800">Ajuntaments i Sector Públic</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-xl font-semibold text-slate-800">Aeroports i Grans Superfícies</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <span class="text-xl font-semibold text-slate-800">Empreses Privades de Transport</span>
                        </div>
                    </div>
                </div>
                <div class="w-full lg:w-1/2 flex justify-center">
                    <div class="relative w-full max-w-md aspect-square bg-gradient-to-tr from-primary-100 to-secondary-50 rounded-full flex items-center justify-center">
                        <div class="absolute inset-4 rounded-full border border-dashed border-primary-300 animate-[spin_30s_linear_infinite]"></div>
                        <div class="w-4/5 h-4/5 bg-white rounded-2xl shadow-2xl p-6 flex flex-col gap-4 z-10 transform transition-transform hover:scale-105">
                            <div class="h-8 bg-slate-100 rounded animate-pulse w-1/3"></div>
                            <div class="h-32 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-center">
                                <svg class="w-12 h-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"/></svg>
                            </div>
                            <div class="space-y-2 mt-auto">
                                <div class="h-4 bg-slate-100 rounded animate-pulse w-full"></div>
                                <div class="h-4 bg-slate-100 rounded animate-pulse w-5/6"></div>
                                <div class="h-4 bg-slate-100 rounded animate-pulse w-4/6"></div>
                            </div>
                            <div class="absolute -right-6 top-1/2 bg-white p-4 rounded-xl shadow-lg border border-slate-50 flex items-center gap-3">
                                <span class="text-2xl">😊</span>
                                <div>
                                    <div class="text-sm font-bold text-slate-800">Molt fàcil</div>
                                    <div class="text-xs text-slate-500">Amigable per a tots</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 bg-gradient-to-br from-primary-600 to-secondary-600 text-center relative overflow-hidden">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')] opacity-30"></div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 observe-me animate-on-scroll">
            <h2 class="text-4xl md:text-5xl font-extrabold text-white mb-6">Llest per revolucionar la teva flota?</h2>
            <p class="text-xl text-primary-100 mb-10 w-3/4 mx-auto">Uneix-te al programari que optimitza els recursos i revoluciona la teva gestió.</p>
            <a href="http://localhost:3000/register" class="inline-block bg-white text-primary-600 font-bold text-xl px-10 py-5 rounded-full shadow-2xl hover:bg-slate-50 transition-all transform hover:scale-105">Contactan's</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded shrink-0 bg-primary-600 flex items-center justify-center text-white font-bold text-sm">S</div>
                <span class="font-bold text-xl text-white">SIMS</span>
            </div>
            <div class="flex space-x-6 text-sm">
                <a href="#" class="hover:text-white transition-colors">Contacte</a>
                <a href="#" class="hover:text-white transition-colors">Privacitat</a>
                <a href="#" class="hover:text-white transition-colors">Termes d'ús</a>
            </div>
            <div class="text-sm">
                &copy; {{ date('Y') }} SIMS. Tots els drets reservats.
            </div>
        </div>
    </footer>

    <!-- Script for scroll animation -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.observe-me').forEach((el) => {
                observer.observe(el);
            });
        });
    </script>
</body>
</html>
