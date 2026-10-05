import React, { useState } from 'react';
import { collection, addDoc } from 'firebase/firestore';
import { db } from './../NavBar/firebaseConfig';
import './Pagamenti.css';

const Pagamenti = ({ appartamento, checkIn, checkOut, numAdulti, numBambini, user }) => {
  const [datiPersonali, setDatiPersonali] = useState({ nome: '', cognome: '', email: '' });
  const [cartaDiCredito, setCartaDiCredito] = useState({ numero: '', scadenza: '', codiceSicurezza: '' });
  const [loading, setLoading] = useState(false);

  const handleDatiPersonaliChange = (field, value) => {
    setDatiPersonali({ ...datiPersonali, [field]: value });
  };

  const handleCartaDiCreditoChange = (field, value) => {
    setCartaDiCredito({ ...cartaDiCredito, [field]: value });
  };

  const handleConfermaPagamento = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      await addDoc(collection(db, 'prenotazioni'), {
        appartamentoId: appartamento.id,
        checkIn: new Date(checkIn),
        checkOut: new Date(checkOut),
        numAdulti,
        numBambini,
        datiPersonali,
        cartaDiCredito,
        email: user?.email, // Utilizza l'email dell'utente se disponibile
      });

      alert('Prenotazione confermata!');
    } catch (error) {
      console.error('Errore durante la conferma del pagamento:', error);
      alert('Si è verificato un errore durante la prenotazione. Riprova.');
    }

    setLoading(false);
  };

  return (
    <form onSubmit={handleConfermaPagamento} className="pagamenti">
      <h3>Conferma pagamento</h3>
      <div className="box">
        <label>Nome</label>
        <input
          type="text"
          value={datiPersonali.nome}
          onChange={(e) => handleDatiPersonaliChange('nome', e.target.value)}
          required
        />
      </div>
      <div className="box">
        <label>Cognome</label>
        <input
          type="text"
          value={datiPersonali.cognome}
          onChange={(e) => handleDatiPersonaliChange('cognome', e.target.value)}
          required
        />
      </div>
      <div className="box">
        <label>Email</label>
        <input
          type="email"
          value={datiPersonali.email}
          onChange={(e) => handleDatiPersonaliChange('email', e.target.value)}
          required
        />
      </div>
      <div className="box">
        <label>Numero di carta di credito</label>
        <input
          type="text"
          value={cartaDiCredito.numero}
          onChange={(e) => handleCartaDiCreditoChange('numero', e.target.value)}
          required
        />
      </div>
      <div className="box">
        <label>Data di scadenza</label>
        <input
          type="text"
          value={cartaDiCredito.scadenza}
          onChange={(e) => handleCartaDiCreditoChange('scadenza', e.target.value)}
          required
        />
      </div>
      <div className="box">
        <label>Codice di sicurezza</label>
        <input
          type="text"
          value={cartaDiCredito.codiceSicurezza}
          onChange={(e) => handleCartaDiCreditoChange('codiceSicurezza', e.target.value)}
          required
        />
      </div>
      <button type="submit" className="btn" disabled={loading}>
        {loading ? 'Processing...' : 'Conferma pagamento'}
      </button>
    </form>
  );
};

export default Pagamenti;
