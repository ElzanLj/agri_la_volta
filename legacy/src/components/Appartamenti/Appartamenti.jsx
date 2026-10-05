import React, { useState } from 'react';
import './Appartamenti.css';
import '../DoveSiamo/DoveSiamo.css';
import ImageSlider from '../ImageSlider/ImageSlider';
import { apartmentsData } from './DatiAppartamenti'; 
import foto from '../../assets/fotoDintorni/appartamentoFoto.jpg';
import SliderApartament from './SliderApartament';
import DescrizioneA from './DescrizioneA';


// Componente Header: contiene il titolo, sottotitolo e un pulsante che scrolla verso la sezione appartamenti
function Header() {
  const scrollToAppartamenti = () => {
    document.getElementById('appartamenti-section').scrollIntoView({ behavior: 'smooth' });
  };

  return (
    <div className="header" style={{ backgroundImage: `linear-gradient(rgba(155, 153, 150, 0.6), rgba(115, 136, 200, 0.6)), url(${foto})` }}>
      <div className="header-content">
        <h1>I nostri Appartamenti</h1>
        <p>AGRITURISMO LA VOLTA</p>
        <button onClick={scrollToAppartamenti}>Scopri i nostri appartamenti</button>
      </div>
    </div>
  );
}


// Componente InfoAggiuntive: mostra quadrati contenenti informazioni aggiuntive sugli appartamenti
const InfoAggiuntive = () => {
  return (
    <div className="info-aggiuntive">
      <h2>Informazioni</h2>
      <div className="info-grid">
        <div className="info-item">
          <h3>Supplementi</h3>
          <p>Per ogni letto aggiuntivo, è previsto un supplemento di €20,00 a persona.</p>
        </div>
        <div className="info-item">
          <h3>Orari</h3>
          <p>Le camere vengono consegnate dopo le 11:00 fino alle 18:00, con possibilità di diversi accordi, e devono essere liberate entro le 9:00 del giorno di partenza.</p>
        </div>
        <div className="info-item">
          <h3>Pulizie</h3>
          <p>È previsto un costo di €30,00 per la pulizia finale degli appartamenti, mentre una pulizia infrasettimanale è disponibile su richiesta al costo di €10,00.</p>
        </div>
        <div className="info-item">
          <h3>Offerte per Famiglie</h3>
          <p>Offriamo uno sconto del 50% per bambini da 1 a 3 anni, mentre i bambini fino a un anno soggiornano gratuitamente. È possibile richiedere la fornitura di un lettino con biancheria al costo di €10,00 al giorno.</p>
        </div>
        <div className="info-item">
          <h3>Offerte Speciali</h3>
          <p>Per periodi di bassa stagione o soggiorni prolungati, siamo disponibili a concordare formule speciali. Non esitate a contattarci per ulteriori informazioni e per organizzare il vostro soggiorno su misura.</p>
        </div>
      </div>
    </div>
  );
};



// Componente principale Appartamenti: visualizza la lista degli appartamenti e i loro dettagli
const Appartamenti = () => {
  // Stato per l'appartamento selezionato: per capire quale appartamento è stato selezionato
  const [selectedApartment, setSelectedApartment] = useState(null);  

  // Funzione per aprire i dettagli di un appartamento
  const openDetails = (apartment) => {
    setSelectedApartment(apartment);
  };

  // Funzione per chiudere i dettagli dell'appartamento
  const closeDetails = () => {
    setSelectedApartment(null);
  };

  return (
    <div>
      <Header />

      <div id="appartamenti-section" className="appartamenti"> 
        <SliderApartament>
          
          {apartmentsData.map((apartment, index) => (
            <div className="appartamento" key={index}>
              <div className="containerStyles">
                <span className="prezzo">{apartment.prezzo}</span>
                <button className="btn" onClick={() => openDetails(apartment)}>Vedi Dettagli</button>
                <ImageSlider slides={apartment.slides} />
              </div>
              <div className="info-appartamento">
                <h3>{apartment.nome}</h3>
                <span>SERVIZI</span>
                <div className="servizi">
                  {apartment.servizi.map((servizio, index) => (
                    <div className="servizio" key={index}>
                      <div className="icona">
                        <i className={`fas ${servizio.icona}`}></i>
                      </div>
                      <div className="testo">
                        <p>{servizio.nome}</p>
                      </div>
                    </div>
                  ))}
                </div>
                <div className="stars">
                  {[...Array(apartment.stelle)].map((_, index) => (
                    <i key={index} className="fas fa-star"></i>
                  ))}
                  {apartment.mezzaStella && (
                    <i className="fas fa-star-half-alt"></i>
                  )}
                  {[...Array(apartment.stelleVuote)].map((_, index) => (
                    <i key={index} className="far fa-star"></i>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </SliderApartament>

        {selectedApartment && (
          <DescrizioneA apartment={selectedApartment} closeDetails={closeDetails} />
        )}
      </div>
      
      <InfoAggiuntive />

    </div>
  );
};

export default Appartamenti;