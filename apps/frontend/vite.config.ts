import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import path from 'node:path'
import { defineConfig, type Plugin } from 'vite'

const apiProxyTarget = process.env.VITE_API_PROXY_TARGET ?? 'http://localhost:8080'

function escapeHtml(value: string) {
  return value.replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character] ?? character)
}

function careersMetadata(): Plugin {
  return {
    name: 'oneflo-careers-metadata',
    transformIndexHtml: {
      order: 'pre',
      async handler(html, context) {
        const match = context.originalUrl?.match(/^\/careers\/jobs\/([a-z0-9-]+)/i)
        if (!match) return html
        try {
          const response = await fetch(`${apiProxyTarget}/api/v1/careers/jobs/${match[1]}`, { headers: { Accept: 'application/json' } })
          if (!response.ok) return html
          const job = ((await response.json()) as { data: unknown }).data as { title: string; company: string; description: string | null; slug: string; company_logo: string | null }
          const host = context.server?.config.server.origin ?? `http://localhost:${context.server?.config.server.port ?? 5173}`
          const title = escapeHtml(`${job.title} at ${job.company}`)
          const description = escapeHtml((job.description ?? `${job.title} at ${job.company}`).replace(/<[^>]*>/g, '').slice(0, 200))
          const url = escapeHtml(`${host}/careers/jobs/${job.slug}`)
          const image = job.company_logo ? escapeHtml(`${host}${job.company_logo}`) : null
          const tags = [
            `<meta property="og:type" content="website" />`,
            `<meta property="og:title" content="${title}" />`,
            `<meta property="og:description" content="${description}" />`,
            `<meta property="og:url" content="${url}" />`,
            image ? `<meta property="og:image" content="${image}" />` : '',
            `<meta name="twitter:card" content="${image ? 'summary_large_image' : 'summary'}" />`,
            `<meta name="twitter:title" content="${title}" />`,
            `<meta name="twitter:description" content="${description}" />`,
            image ? `<meta name="twitter:image" content="${image}" />` : '',
          ].filter(Boolean).join('\n    ')
          return html.replace(/<title>.*?<\/title>/, `<title>${title}</title>`).replace('</head>', `    ${tags}\n  </head>`)
        } catch {
          return html
        }
      },
    },
  }
}

export default defineConfig({
  plugins: [react(), tailwindcss(), careersMetadata()],
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, 'src'),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    watch: {
      usePolling: process.env.CHOKIDAR_USEPOLLING === 'true',
    },
    proxy: {
      '/api': {
        target: apiProxyTarget,
        changeOrigin: true,
      },
      '/sanctum': {
        target: apiProxyTarget,
        changeOrigin: true,
      },
    },
  },
})
