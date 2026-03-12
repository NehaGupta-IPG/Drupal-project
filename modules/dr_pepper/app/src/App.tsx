import { useState } from 'react'
import './App.css'

function App() {
  const [count, setCount] = useState(0)

  return (
    <div style={{marginLeft: '150px'}}>
      <h1>I am a heading</h1>
      <div className="card">
        <button onClick={() => setCount((count) => count + 1)}>
          count is {count}
        </button>
        <h1>testing</h1>
        <p>
          Lets goooo
        </p>
      </div>
    </div>
  )
}

export default App
