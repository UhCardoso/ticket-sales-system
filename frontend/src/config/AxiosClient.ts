import axios, { AxiosError, type AxiosInstance } from 'axios'

/**
 * The single axios instance for the API, plus the error type the rest of the app sees.
 *
 * Exported as default and imported only by `services/`. Nothing above that layer — no view, no
 * composable — imports axios or this instance to make a call; the one thing they may import is
 * `ApiError`, to tell a cancelled poll from a real failure.
 *
 * Failures are normalised here so no layer above has to know about axios: a caller gets an
 * `ApiError` with a reason it can act on, and `aborted` is kept apart from the rest because
 * a cancelled poll is routine, not something to show the user.
 *
 * There is no token, loading or toast interceptor because this panel has no auth and no toast
 * layer — when one arrives, it belongs here, not in a service.
 */

export type ApiErrorReason = 'aborted' | 'timeout' | 'network' | 'server' | 'client' | 'unknown'

export class ApiError extends Error {
  constructor(
    readonly reason: ApiErrorReason,
    message: string,
    readonly status?: number,
  ) {
    super(message)
    this.name = 'ApiError'
  }

  /** Whether the request was cancelled on purpose — a stale poll, a closed view. */
  get isAborted(): boolean {
    return this.reason === 'aborted'
  }
}

const DEFAULT_TIMEOUT = 10_000

const apiClient: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api',
  timeout: Number(import.meta.env.VITE_API_TIMEOUT) || DEFAULT_TIMEOUT,
  headers: { Accept: 'application/json' },
})

apiClient.interceptors.response.use(
  (response) => response,
  (error: unknown) => Promise.reject(toApiError(error)),
)

export default apiClient

/**
 * Translates whatever axios threw into an ApiError with a usable reason.
 */
function toApiError(error: unknown): ApiError {
  if (!axios.isAxiosError(error)) {
    return new ApiError('unknown', error instanceof Error ? error.message : 'Erro inesperado.')
  }

  const axiosError = error as AxiosError

  if (axios.isCancel(axiosError) || axiosError.code === 'ERR_CANCELED') {
    return new ApiError('aborted', 'Requisição cancelada.')
  }

  if (axiosError.code === 'ECONNABORTED' || axiosError.code === 'ETIMEDOUT') {
    return new ApiError('timeout', 'O servidor demorou demais para responder.')
  }

  const status = axiosError.response?.status

  if (status === undefined) {
    return new ApiError('network', 'Não foi possível falar com o servidor.')
  }

  if (status >= 500) {
    return new ApiError('server', 'O servidor respondeu com erro.', status)
  }

  return new ApiError('client', messageFromBody(axiosError) ?? 'Requisição recusada.', status)
}

/**
 * Pulls Laravel's `message` out of an error body, when there is one.
 */
function messageFromBody(error: AxiosError): string | undefined {
  const body = error.response?.data

  if (body && typeof body === 'object' && 'message' in body) {
    const message = (body as { message: unknown }).message

    return typeof message === 'string' ? message : undefined
  }

  return undefined
}
