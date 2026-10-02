import apiClient from '@/config/AxiosClient'
import type { SalesSummaryResponse } from '@/models/SalesSummaryModel'

/**
 * The only class that knows the sales dashboard endpoints.
 *
 * One class per API resource, stateless: it owns the path, the verb and the payload, unwraps
 * `response.data`, and returns it untransformed. No caching, no derived values and no `try/catch`
 * here — errors are already normalised by the interceptor, and whatever the panel derives from
 * these numbers belongs to the composable.
 */

const path = '/dashboard'

export default class SalesDashboardService {
  /**
   * Fetches the snapshot of every event and batch on sale.
   *
   * @param signal Aborts the request when the poll is superseded or the view unmounts
   */
  async getSalesSummary(signal?: AbortSignal): Promise<SalesSummaryResponse> {
    const response = await apiClient.get<SalesSummaryResponse>(`${path}/sales-summary`, { signal })

    return response.data
  }
}
