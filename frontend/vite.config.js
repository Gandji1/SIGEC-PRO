import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  base: '/',
  plugins: [
    react({
      // Optimisation React - fast refresh
      fastRefresh: true,
    })
  ],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
        rewrite: (path) => path
      }
    }
  },
  // Optimisations de performance
  optimizeDeps: {
    include: ['react', 'react-dom', 'react-router-dom', 'axios', 'zustand', 'lucide-react'],
    exclude: ['recharts'], // Lazy load recharts
  },
  build: {
    outDir: 'dist',
    sourcemap: false,
    minify: 'terser',
    chunkSizeWarningLimit: 300,
    // Options Terser pour minification agressive
    terserOptions: {
      compress: {
        drop_console: true, // Supprimer console.log en prod
        drop_debugger: true,
        pure_funcs: ['console.log', 'console.info'],
      },
      mangle: true,
    },
    rollupOptions: {
      output: {
        dir: 'dist',
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
        // Code splitting manuel pour les vendors lourds
        manualChunks: {
          // React core - chargé en premier
          'vendor-react': ['react', 'react-dom', 'react-router-dom'],
          // Graphiques (recharts est lourd ~200kb) - lazy load
          'vendor-charts': ['recharts'],
          // Utilitaires légers
          'vendor-utils': ['axios', 'zustand'],
          // Icons - tree-shakable
          'vendor-icons': ['lucide-react'],
        }
      }
    },
    // Compression CSS
    cssCodeSplit: true,
    cssMinify: true,
  },
  // Préchargement des modules
  esbuild: {
    legalComments: 'none',
    treeShaking: true,
  }
})
