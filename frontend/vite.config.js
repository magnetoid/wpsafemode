import { defineConfig } from 'vite'
import preact from '@preact/preset-vite'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [preact(), tailwindcss()],

  // Relative asset paths are mandatory: the tool is installed into a randomly
  // named directory (spec 5.2), so absolute /assets/... URLs would 404.
  base: './',

  build: {
    outDir: '../src/public',
    emptyOutDir: true,
  },

  server: {
    proxy: {
      '/api': 'http://localhost:8080/wpsafemode/index.php',
    },
  },
})
