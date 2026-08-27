import React, { useEffect, useState } from 'react';
import { collection, getDocs } from 'firebase/firestore';
import { db } from './firebaseConfig';

const ListaAppartamenti = () => {
  const [appartamenti, setAppartamenti] = useState([]);

  useEffect(() => {
    const fetchAppartamenti = async () => {
      try {
        const appartamentiRef = collection(db, 'appartamenti');
        const snapshot = await getDocs(appartamentiRef);
        const appartamentiData = snapshot.docs.map(doc => ({
          id: doc.id,
          ...doc.data()
        }));
        setAppartamenti(appartamentiData);
      } catch (error) {
        console.error('Errore durante il recupero degli appartamenti:', error);
      }
    };

    fetchAppartamenti();
  }, []);

  return (
    <div>
      <h2>Lista Appartamenti</h2>
      <ul>
        {appartamenti.map(appartamento => (
          <li key={appartamento.id}>
            <h3>{appartamento.nome}</h3>
            <p>Check-in: {appartamento.checkIn.toDate().toLocaleDateString()}</p>
            <p>Check-out: {appartamento.checkOut.toDate().toLocaleDateString()}</p>
            <p>Adulti: {appartamento.adulti}</p>
            <p>Bambini: {appartamento.bambini}</p>
          </li>
        ))}
      </ul>
    </div>
  );
};

export default ListaAppartamenti;
