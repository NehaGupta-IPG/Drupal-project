import { createStore, isPlainObject, combineReducers } from 'redux';
import { createSlice } from 'redux-toolkit';
// import { produce } from 'immer';
// Define a reducer

const counter = (state = 0, action) => {
  switch (action.type) {
    case 'INCREMENT':
      return state + 1;
    case 'DECREMENT':
      return state - 1;
    default:
      return state;
  }
};
console.dir(createSlice);
// console.dir(configureStore);

// Create a Redux store
let store = createStore(counter);
// console.log(configureStore);

// Dispatch some actions
store.dispatch({ type: 'INCREMENT' });
console.log(store.getState()); // 1
store.dispatch({ type: 'INCREMENT' });
console.log(store.getState()); // 2
store.dispatch({ type: 'DECREMENT' });
console.log(store.getState()); // 1
