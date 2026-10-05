import React from 'react'
import './DoveSiamo.css'
import ImageSlider from '../ImageSlider/ImageSlider';

import Navbar from '../NavBar/NavBar';
import Torrechiara from '../../assets/fotoDintorni/Torrechiara.jpeg'
import Fontanellato from '../../assets/fotoDintorni/castello-fontanellato.jpeg'
import Tabiano from '../../assets/fotoDintorni/CastelloTabiano.jpeg'
import Vigoleno from '../../assets/fotoDintorni/vigoleno.jpeg'
import TermeSalso from '../../assets/fotoDintorni/Berzieri.jpeg'
import panorama1 from '../../assets/fotoDintorni/fotoAgri2.jpeg'
import busseto from '../../assets/fotoDintorni/Busseto.jpeg'
import stirone from '../../assets/fotoDintorni/Stirone2.jpeg'



function Header() {
  return (
    <div className="header">
      <div className="header-content">
        <h1>Dove siamo e cosa visitare nei dintorni</h1>
        <p>Scopri cosa visitare nei dintorni dell'agriturismo la Volta </p>
      </div>
      <div className="background-image"></div>
    </div>
  );
}



function MapSection() {
  return (
    <div className="map-section">
      <div className="map">
        <iframe className="mappa" src="https://www.google.com/maps/embed?pb=!1m23!1m12!1m3!1d78417.13412710567!2d9.881743815991552!3d44.800045936845116!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!4m8!3e6!4m0!4m5!1s0x47808a266ef44623%3A0xa87127cfd48ca08d!2sAgriturismo%20%22LA%20VOLTA%22%2C%20Localit%C3%A0%20Marzano%2C%20Salsomaggiore%20Terme%2C%20PR!3m2!1d44.7982556!2d9.9651322!5e0!3m2!1sit!2sit!4v1713883593705!5m2!1sit!2sit"/>
      </div>  
      <div className="additional-content">  
        <h2>La nostra posizione</h2>
        <p>L'Agriturismo La Volta si trova sulle dolci colline della provincia di Parma, una zona ricca di bellezze naturali e storiche. Immerso nel verde, è il luogo ideale per chi desidera rilassarsi e godere di una vista incantevole, lontano dal caos cittadino.</p>
      </div>
    </div>
  );
}

function CastleSection() {
  const slides = [Vigoleno, Torrechiara, Fontanellato, Tabiano];
  return (
    <div className="map-section">
      <div className="map">
        <ImageSlider slides={slides} />     
      </div>  
      <div className="additional-content">  
        <h2>Scopri i Castelli</h2>
        <p>Visita i meravigliosi castelli della zona, testimoni di un passato ricco di storia e tradizione. Come ad esempio il castello di Tabiano, il castello di Vigoleno, Torrechiara...</p>
      </div>
    </div>
  );
}

function CastleVisit(props) {
  return (
    <div className="castle-visit" style={{ backgroundImage: `url(${props.backgroundImage})` }}>
      <div className="castle-visit-content">
        {props.additionalText && <div>{props.additionalText}</div>}
      </div>
    </div>
  );
}

const Footer = () => {
  return (
    <div className="footer">
      <div className="contact-info">
        <p>Email: info@agriturismolavolta.it</p>
        <p>Telefono: +39 338 577 2918</p>
      </div>
      <div className="social-icons">
        {/* <i className="fab fa-facebook-f"></i> */}
      </div>
    </div>
  );
};

const DoveSiamo = () => {
  return (
    <div>
      <Navbar/> 
      <Header/>
      <CastleVisit 
        backgroundImage={panorama1} 
        additionalText={
          <>
            <h2>Rilassati sulle colline di Salsomaggiore</h2>
            <p>Goditi un soggiorno immerso nella tranquillità della campagna parmense, con viste panoramiche che ti faranno sentire in armonia con la natura.</p>
          </>} 
      />
      <MapSection />
      <CastleVisit 
        backgroundImage={TermeSalso} 
        additionalText={
          <>
            <h2 style={{ textShadow: '1px 1px 1px black' }}>Terme Berzieri</h2>
            <p style={{ textShadow: '1px 1px 1px black' }}>Scopri il fascino delle Terme Berzieri, un luogo di benessere e storia, perfetto per una pausa rigenerante. Offrono trattamenti termali rigeneranti grazie alle acque di Salsomaggiore, rinomate per le proprietà curative e rilassanti. </p>
          </>
        } 
      />
      <CastleSection/>
      <CastleVisit 
        backgroundImage={stirone} 
        additionalText={
          <>
            <h2>Parco dello Stirone</h2>
            <p>Esplora il Parco dello Stirone, un'area protetta ideale per passeggiate nella natura, birdwatching e scoprire la flora e la fauna locali.</p>
          </>} 
      />  
      <CastleVisit 
        backgroundImage={busseto} 
        additionalText={
          <>
            <h2>Busseto e luoghi Verdiani</h2>
            <p>Visita la città natale di Giuseppe Verdi, dove potrai ammirare il teatro a lui dedicato, la casa natale, Rocca Pallavicino e molte altre attrazioni legate al grande compositore.</p>
          </>} 
      />
      <Footer />
    </div>
  )
}

export default DoveSiamo
