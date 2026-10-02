import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'

/**
 * Routes and their metadata. Every view is reached from here and nowhere else.
 *
 * The component is always a dynamic `import()`, so a view is only downloaded when its route is
 * visited — which is also why views live under `views/<Area>/` and never get imported by a
 * component.
 */

declare module 'vue-router' {
  interface RouteMeta {
    /** Becomes the browser tab title. */
    title?: string
  }
}

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    redirect: { name: 'sales-dashboard' },
  },
  {
    path: '/painel',
    name: 'sales-dashboard',
    component: () => import('@/views/Dashboard/SalesDashboardView.vue'),
    meta: { title: 'Painel de vendas' },
  },
]

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.afterEach((to) => {
  document.title = to.meta.title ?? 'Painel de vendas'
})

export default router
