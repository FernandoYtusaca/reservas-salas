const BASE_URL = import.meta.env.VITE_API_URL

export class ApiError extends Error {
  constructor(status, data) {
    super(data?.message || 'Error en la petición')
    this.status = status
    this.errors = data?.errors || {}
  }
}

async function request(path, { method = 'GET', body, params } = {}) {
  const url = new URL(BASE_URL + path)

  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        url.searchParams.set(key, value)
      }
    })
  }

  const headers = { Accept: 'application/json' }
  const token = localStorage.getItem('token')
  if (token) headers.Authorization = `Bearer ${token}`

  const options = { method, headers }
  if (body) {
    headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(body)
  }

  const response = await fetch(url, options)

  if (response.status === 204) return null

  const data = await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401 && token) {
      localStorage.removeItem('token')
      window.location.assign('/login')
    }
    throw new ApiError(response.status, data)
  }

  return data
}

export const api = {
  get: (path, params) => request(path, { params }),
  post: (path, body) => request(path, { method: 'POST', body }),
  put: (path, body) => request(path, { method: 'PUT', body }),
  delete: (path) => request(path, { method: 'DELETE' }),
}
