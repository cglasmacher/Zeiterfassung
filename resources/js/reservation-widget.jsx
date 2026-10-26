import React from 'react';
import {createRoot} from 'react-dom/client';
import ReservationWidget from './Widgets/ReservationWidget';
import './Widgets/reservationWidget.css';
const root=document.getElementById('reservation-widget');
createRoot(root).render(<ReservationWidget configuration={JSON.parse(root.dataset.configuration)}/>);
