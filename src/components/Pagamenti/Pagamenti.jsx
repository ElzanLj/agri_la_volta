import React, { useState } from 'react';
import './Pagamenti.css';

// NOTA IMPORTANTE SUL PAGAMENTO
// In precedenza questo componente raccoglieva numero di carta, scadenza e CVV
// e li salvava in chiaro su Firestore. Questo è stato rimosso: memorizzare
// dati di carta di credito non cifrati (specialmente il CVV, che non va MAI
// salvato) viola gli standard di sicurezza PCI-DSS ed espone sia te che i
// tuoi clienti a un rischio serio in caso di violazione dei dati.
//
// Qui sotto si raccolgono solo i dati di contatto necessari per confermare
// la prenotazione. Per accettare pagamenti reali con carta, il modo corretto
// è integrare un gestore di pagamenti certificato PCI (es. Stripe o PayPal):
// il numero di carta passa direttamente dal browser del cliente al loro
// server, e tu ricevi solo una conferma, senza mai toccare i dati sensibili.

const Pagamenti = ({ onConfirm, disabled }) => {
  const [datiPersonali, setDatiPersonali] = useState({ nome: '', cognome: '', email: '', telefono: '' });
  const [submitting, setSubmitting] = useState(false);

  const handleChange = (field, value) => {
    setDatiPersonali((prev) => ({ ...prev, [field]: value }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (submitting || disabled) return;
    setSubmitting(true);
    try {
      await onConfirm(datiPersonali);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="pagamenti">
      <h3>I tuoi dati di contatto</h3>
      <p className="pagamenti-nota">
        Il pagamento verrà perfezionato in struttura o tramite un link di pagamento sicuro
        che ti invieremo via email dopo la conferma.
      </p>
      <div className="box">
        <label>Nome</label>
        <input
          type="text"
          value={datiPersonali.nome}
          onChange={(e) => handleChange('nome', e.target.value)}
          required
        />
      </div>
      <div className="box">
        <label>Cognome</label>
        <input
          type="text"
          value={datiPersonali.cognome}
          onChange={(e) => handleChange('cognome', e.target.value)}
          required
        />
      </div>
      <div className="box">
        <label>Email</label>
        <input
          type="email"
          value={datiPersonali.email}
          onChange={(e) => handleChange('email', e.target.value)}
          required
        />
      </div>
      <div className="box">
        <label>Telefono</label>
        <input
          type="tel"
          value={datiPersonali.telefono}
          onChange={(e) => handleChange('telefono', e.target.value)}
          required
        />
      </div>
      <button type="submit" className="btn" disabled={submitting || disabled}>
        {submitting ? 'Invio in corso...' : 'Conferma prenotazione'}
      </button>
    </form>
  );
};

export default Pagamenti;
