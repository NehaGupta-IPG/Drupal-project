import React from 'react';
import ReactDOM from 'react-dom';
// import Babel from '@babel/standalone';
// import Foo from 'foo';
// import {
//   createRoot
// } from '../../../assets/vendor/react-dom/react-dom.development';

// const {parser, generator} = Babel.packages;
// const {transformFromAst, transform} = Babel;

const jsx = `<h1 style={{marginLeft: '80px'}} onClick={() => console.log('clicked')}>Hello world!</h1>`;

// AST
const ast = parser.parse(jsx, {
  sourceType: 'module',
  plugins: ['jsx'],
});

const { code } = transformFromAst(ast, '', {
  presets: ['react'],
});

const domNode = document.getElementById('react-app');
const root = ReactDOM.createRoot(domNode);
root.render(eval(code));
