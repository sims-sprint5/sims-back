# Implementación de Roles y Permisos en el Frontend

## 1. Obtener el Rol del Usuario
- Asegúrate de que el backend envíe el rol del usuario en la respuesta de inicio de sesión o en un endpoint específico. Ejemplo de respuesta del backend:
  ```json
  {
    "user": {
      "id": 1,
      "name": "Usuario",
      "role": "Usuario"
    },
    "token": "your-auth-token"
  }
  ```

## 2. Almacenar el Rol del Usuario
- Guarda el rol del usuario en el estado global del frontend (por ejemplo, usando Context API, Redux o Pinia). Ejemplo con Context API:
  ```javascript
  import { createContext, useContext, useState } from 'react';

  const AuthContext = createContext();

  export const AuthProvider = ({ children }) => {
    const [user, setUser] = useState(null);

    const login = (userData) => {
      setUser(userData);
    };

    return (
      <AuthContext.Provider value={{ user, login }}>
        {children}
      </AuthContext.Provider>
    );
  };

  export const useAuth = () => useContext(AuthContext);
  ```

## 3. Proteger Rutas Según el Rol
- Implementa un sistema de rutas protegidas para verificar el rol del usuario antes de permitir el acceso. Ejemplo con React Router:
  ```javascript
  import { Navigate } from 'react-router-dom';
  import { useAuth } from './AuthContext';

  const ProtectedRoute = ({ children, allowedRoles }) => {
    const { user } = useAuth();

    if (!user || !allowedRoles.includes(user.role)) {
      return <Navigate to="/unauthorized" />;
    }

    return children;
  };

  export default ProtectedRoute;
  ```

  Uso:
  ```javascript
  <Route
    path="/admin"
    element={
      <ProtectedRoute allowedRoles={["Admin"]}>
        <AdminPage />
      </ProtectedRoute>
    }
  />
  ```

## 4. Mostrar Funcionalidades Basadas en el Rol
- Condiciona la visualización de botones, menús o secciones según el rol del usuario. Ejemplo:
  ```javascript
  import { useAuth } from './AuthContext';

  const Dashboard = () => {
    const { user } = useAuth();

    return (
      <div>
        <h1>Bienvenido, {user?.name}</h1>
        {user?.role === 'Admin' && <button>Gestionar Usuarios</button>}
        {user?.role === 'Usuario' && <button>Ver Mis Tickets</button>}
      </div>
    );
  };

  export default Dashboard;
  ```

## 5. Inicializar Sanctum para Autenticación
- Antes de realizar solicitudes autenticadas, inicializa el CSRF token con Sanctum:
  ```javascript
  import axios from 'axios';

  const api = axios.create({
    baseURL: 'http://localhost:8000', // URL del backend
    withCredentials: true, // Permite el envío de cookies
  });

  const initializeSanctum = async () => {
    await api.get('/sanctum/csrf-cookie');
  };

  export { api, initializeSanctum };
  ```

## 6. Pruebas
- Verifica que:
  - Los usuarios con rol `Usuario` solo puedan acceder a sus funcionalidades.
  - Los usuarios con rol `Admin` puedan acceder a funcionalidades avanzadas.
  - Los usuarios no autenticados sean redirigidos a la página de inicio de sesión.