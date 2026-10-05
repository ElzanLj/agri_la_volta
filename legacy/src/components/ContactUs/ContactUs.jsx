// ContactUs.jsx

import React from 'react';
import './ContactUs.css'; 
import tripadvisorLogo from '../../assets/icon/tripadvisorLogo.svg'; 


// Dati di contatto. Il modulo di contatto del sito originale usava il server Node
// rimosso (src/EmailStatus): sarà sostituito dal modulo PHP della nuova architettura.
function ContactUs() {
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
