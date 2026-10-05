import React from 'react'
import './DescrizioneA.css';

const DescrizioneA = ({ apartment, closeDetails }) => {
  return (
    <div className="modal-overlay">
      <div className="modal-content">
        <button className="close-button" onClick={closeDetails}>x</button>
        <img src={apartment.slides[0]} alt={apartment.nome} className="modal-image" />
        <div className="modal-info">
          <h2>{apartment.nome}</h2>
          <p>{apartment.prezzo}</p>
          <div className="descrizione">
            <p>{apartment.descrizione}</p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DescrizioneA
