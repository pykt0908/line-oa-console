import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { aliases, mdi } from 'vuetify/iconsets/mdi'
import 'vuetify/styles'

export default createVuetify({
  components,
  directives,
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: { mdi },
  },
  theme: {
    defaultTheme: 'stech',
    themes: {
      stech: {
        dark: false,
        colors: {
          primary: '#0B8F45',
          secondary: '#0A6E38',
          tertiary: '#148F4F',
          error: '#B42318',
          warning: '#B54708',
          info: '#2563EB',
          success: '#0B8F45',
          background: '#F2F4F3',
          surface: '#FFFFFF',
          'on-primary': '#FFFFFF',
          'on-surface': '#101820',
          'surface-variant': '#E7F6EC',
        },
      },
    },
  },
  defaults: {
    VBtn: {
      rounded: 'md',
      style: 'text-transform: none; letter-spacing: 0; font-weight: 600;',
    },
    VTextField: { variant: 'outlined', density: 'compact', hideDetails: 'auto' },
    VSelect: { variant: 'outlined', density: 'compact', hideDetails: 'auto' },
    VChip: { density: 'compact', rounded: 'md' },
  },
})
