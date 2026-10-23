import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import MorePage from './MorePage';
import './styles.css';

window.HemingwayMorePage = MorePage;

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode><App /></React.StrictMode>
);
