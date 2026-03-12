import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.tsx'
import './index.css'
import 'vite/modulepreload-polyfill'

ReactDOM.createRoot(document.getElementById('react-app')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
