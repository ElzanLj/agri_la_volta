import React from 'react';
import './Hero.css';

const Hero = () => {
  return (
    <div className='hero-container' id="hero">
      <div className='hero-text'>
        <h1>Un agriturismo con piscina immerso nel verde</h1>
        <p>
        L'agriturismo La Volta è la meta perfetta per un tranquillo e rilassato soggiorno nelle colline salsesi, inoltre potrete godervi di una vista mozzafiato.
        </p>
        {/* 
        <button className='btn'>
          Vedi di più
          <box-icon animation='tada-hover' name='right-arrow-alt' color="var(--primary)" />
        </button>
        */}
      </div>
      <div className='hero-bottom-image'></div>
    </div>
  );
};

export default Hero;
