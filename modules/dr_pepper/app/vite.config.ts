import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
console.log('hi');
export default defineConfig({
  plugins: [react()],
  build: {
    rollupOptions: {
      // External dependencies
      external: ['react', 'react-dom'],
      output: {
        // Override the default naming convention here
        entryFileNames: `assets/[name].js`,
        chunkFileNames: `assets/[name].js`,
        assetFileNames: `assets/[name].[ext]`
      },
      input: 'src/main.tsx',
    },
    manifest: true,

  }
})
