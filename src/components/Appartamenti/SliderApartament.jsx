// slider delle schede appartamento
import React, { useEffect, useRef, useState } from 'react';
import './SliderApartament.css';

const SliderApartament = ({ children }) => {

   // Stato per memorizzare l'indice della diapositiva corrente
   const [index, setIndex] = useState(0);
   // Stato per memorizzare il numero di elementi visibili
   const [visibleItems, setVisibleItems] = useState(3);
   // Riferimento per gestire il timeout dell'auto-cambio diapositiva
   const timeoutRef = useRef(null);
   // Riferimento per gestire il timeout del riavvio del cambio diapositiva
   const restartTimeoutRef = useRef(null);
 
  // Funzione per aggiornare il numero di elementi visibili in base alla larghezza della finestra
  const updateVisibleItems = () => {
    const width = window.innerWidth;
    if (width <= 480) {
      setVisibleItems(1);
    } else if (width <= 1024) {
      setVisibleItems(2);
    } else {
      setVisibleItems(3);
    }
  };

  // Effetto per aggiornare gli elementi visibili e aggiungere il listener per il ridimensionamento della finestra
  useEffect(() => {
    updateVisibleItems();
    window.addEventListener('resize', updateVisibleItems);

    return () => {
      window.removeEventListener('resize', updateVisibleItems);
    };
  }, []);

  // Funzione per resettare il timeout dell'auto-cambio diapositiva
  const resetTimeout = () => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }
  };

  // Effetto per gestire il cambio automatico delle diapositive
  useEffect(() => {
    resetTimeout();
    timeoutRef.current = setTimeout(
      () => setIndex((prevIndex) => (prevIndex === children.length - visibleItems ? 0 : prevIndex + 1)),
      4000 // cambia ogni 4 secondi 
    );

    return () => {
      resetTimeout(); //resetta il timeout
    };
  }, [index, visibleItems, children.length]);


  // Funzione per gestire il clic su un elemento della diapositiva
  const handleItemClick = () => {
    resetTimeout();
    if (restartTimeoutRef.current) {
      clearTimeout(restartTimeoutRef.current);
    }
    restartTimeoutRef.current = setTimeout(() => {
      timeoutRef.current = setTimeout(
        () => setIndex((prevIndex) => (prevIndex === children.length - visibleItems ? 0 : prevIndex + 1)),
        4000 // cambia ogni 4 secondi 
      );
    }, 10000); // ricomincia dopo 20 secondi
  };

  return (
    <div className={`slider ${visibleItems === 1 ? 'slider-single' : ''}`}>
      <div
        className="slider-inner"x
        style={{ transform: `translateX(${-index * (100 / visibleItems)}%)` }}
      >
        {children.map((child, idx) => (
          <div 
            className="slider-item" 
            key={idx} 
            style={{ flex: `0 0 ${100 / visibleItems}%` }}
            onClick={handleItemClick}
          >
            {child}
          </div>
        ))}
      </div>
    </div>
  );
};

export default SliderApartament;
