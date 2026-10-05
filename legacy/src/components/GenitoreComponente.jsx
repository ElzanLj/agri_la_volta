import React, { useState } from 'react';
import { collection, addDoc } from 'firebase/firestore';
import { db } from './firebaseConfig';
import BarraDisponibilita from './BarraDisponibilita';

const GenitoreComponente = () => {
  const [prenotazioneEffettuata, setPrenotazioneEffettuata] = useState(false);

  const handlePrenota = async (appartamentoId) => {
    try {
      await addDoc(collection(db, 'prenotazioni'), {
        appartamentoId,
        checkIn,
        checkOut,
        numAdulti,
        numBambini
      });
      setPrenotazioneEffettuata(true);
    } catch (error) {
      console.error('Errore durante la prenotazione:', error);
      alert('Si è verificato un errore durante la prenotazione. Riprova.');
    }
  };

  return (
    <div>
      {!prenotazioneEffettuata ? (
        <BarraDisponibilita
          checkIn={checkIn}
          checkOut={checkOut}
          onPrenota={handlePrenota}
        />
      ) : (
        <p>Prenotazione effettuata con successo!</p>
      )}
    </div>
  );
};

export default GenitoreComponente;