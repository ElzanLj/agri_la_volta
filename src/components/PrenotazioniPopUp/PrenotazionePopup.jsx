import React from 'react';
import './PrenotazionePopup.css';
import BarraDisponibilità from './BarraDisponibilità';
import Pagamenti from './Pagamenti';

const PrenotazionePopup = ({ onClose }) => {
  return (
    <div className="prenotazione-popup-overlay">
      <div className="prenotazione-popup">
        <button className="close-button" onClick={onClose}>X</button>
        <BarraDisponibilità />
        <Pagamenti />
      </div>
    </div>
  );
};

export default PrenotazionePopup;
