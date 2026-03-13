import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Initialise le cookie CSRF de Sanctum avant chaque requête mutante
let csrfInitialized = false;
async function ensureCsrf() {
  if (!csrfInitialized) {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
    csrfInitialized = true;
  }
}

api.interceptors.request.use(async (config) => {
  const mutating = ['post', 'put', 'patch', 'delete'];
  if (config.method && mutating.includes(config.method.toLowerCase())) {
    await ensureCsrf();
  }
  return config;
});

api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      csrfInitialized = false;
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

export default api;
