import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import MorePage from './MorePage';
import './styles.css';
import './more.css';
import './haccp.css';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode><App /><MorePage /></React.StrictMode>
);
