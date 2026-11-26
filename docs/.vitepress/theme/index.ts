import { h } from 'vue'
import DefaultTheme from 'vitepress/theme'
import 'carve-grammars/shiki/carve.css'
import './custom.css'

export default {
  extends: DefaultTheme,
  Layout: () => h(DefaultTheme.Layout, null, {
    'layout-top': () => import.meta.env.VITE_DOCS_SERIES === 'dev'
      ? h('div', { class: 'docs-dev-banner', role: 'status' }, 'Development version, not released')
      : null,
  }),
}
