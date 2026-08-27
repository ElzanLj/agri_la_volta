// ContactUs.jsx

import React, { useState, useEffect } from 'react'; // Importa React e i hook useState e useEffect
import './ContactUs.css'; 
import tripadvisorLogo from '../../assets/icon/tripadvisorLogo.svg'; 


// Funzione principale del componente ContactUs che accetta un prop `user`
function ContactUs({ user }) {
    
    // Stato locale per memorizzare i dati del modulo di contatto
    const [contact, setContact] = useState({
        firstName: '',
        lastName: '',
        email: '',
        phone: '',
        reason: '',
        message: ''
    });

    // Effetto per aggiornare i dati del modulo se l'utente è loggato
    useEffect(() => {
        if (user) {
            setContact(prevContact => ({
                ...prevContact,
                firstName: user.firstName || '',
                lastName: user.lastName || '',
                email: user.email || ''
            }));
        }
    }, [user]);

    // Funzione per gestire i cambiamenti negli input del modulo
    const handleInputChange = (event) => {
        const { name, value } = event.target;
        setContact(prev => ({
            ...prev,
            [name]: value
        }));
    };

    // Funzione per gestire l'invio del modulo
    const handleSubmit = async (event) => {
        event.preventDefault(); // Previene il comportamento di default del form

        // Invia i dati al server usando fetch
        const response = await fetch('http://localhost:3001/send-email', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(contact),
        });

        if (response.ok) {
            alert('Email inviata con successo!');
        } else {
            alert('Errore nell\'invio dell\'email');
        }
    };

    

    return (
        <div className="contactus-container">
            {/* Informazioni di contatto */}
            <div className="contact-info">
                <div className="row">
                    <p>Indirizzo: Marzano, Salsomaggiore Terme, (PR)</p>
                    <p>Email: info@agriturismolavolta.com</p>
                </div>
                <div className="row">
                    <p>Tel: 0524587057</p>
                    <p>Cell: +39 3385772918</p>
                </div>
            </div>
            {/* Modulo di contatto */}
            <form className="contact-form" onSubmit={handleSubmit}>
                <div className="row-input-group">
                    <div className="row-input">
                        <input
                            type="text"
                            name="firstName"
                            value={contact.firstName}
                            onChange={handleInputChange}
                            placeholder="Nome"
                        />
                    </div>
                    <div className="row-input">
                        <input
                            type="text"
                            name="lastName"
                            value={contact.lastName}
                            onChange={handleInputChange}
                            placeholder="Cognome"
                        />
                    </div>
                </div>
                <div className="row-input-group">
                    <div className="row-input">
                        <input
                            type="email"
                            name="email"
                            value={contact.email}
                            onChange={handleInputChange}
                            placeholder="Email"
                        />
                    </div>
                    <div className="row-input">
                        <input
                            type="text"
                            name="phone"
                            value={contact.phone}
                            onChange={handleInputChange}
                            placeholder="Numero di telefono"
                        />
                    </div>
                </div>
                <select name="reason" value={contact.reason} onChange={handleInputChange} className="row-input">
                    <option value="">Seleziona un motivo</option>
                    <option value="Richiesta Disponibilitá">Richiesta Disponibilitá</option>
                    <option value="Informazioni">Informazioni</option>
                    <option value="Affitto Sala Per Eventi">Affitto Sala Per Eventi</option>
                    <option value="Generalitá">Generalitá</option>
                </select>
                <textarea
                    name="message"
                    value={contact.message}
                    onChange={handleInputChange}
                    placeholder="Note:"
                    rows="4"
                    className="row-input"
                />
                <button color="lightgrey" type="submit">Invia</button>
            </form>
            {/* Informazioni aggiuntive e logo TripAdvisor */}
            <div className="contact-info">
                <p>
                    <a href='https://www.tripadvisor.it/Hotel_Review-g194892-d2459540-Reviews-La_Volta-Salsomaggiore_Terme_Province_of_Parma_Emilia_Romagna.html' target='_blank' rel="noreferrer">
                        <span className="logo"><img src={tripadvisorLogo} alt="TripAdvisor Logo" /></span>
                    </a>
                    <span className="logo-text">
                        "La Volta" di Oretti Giuseppe Società Agricola - P.I. 02331450342 - REA 229178  | Privacy Policy
                    </span>
                </p>
            </div>
        </div>
    );
}

export default ContactUs;
