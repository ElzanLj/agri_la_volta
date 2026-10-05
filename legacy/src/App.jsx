import React from 'react'; 
import { BrowserRouter as Router, Route, Routes } from 'react-router-dom';
import './App.css'; 
import NavBar from './components/NavBar/NavBar'; 
import ImageScroller from './components/ImageScroller/ScrollImage'; 
import ContactUs from './components/ContactUs/ContactUs'; 
import Appartamenti from './components/Appartamenti/Appartamenti'; 
import Hero from './components/Hero/Hero';
import DoveSiamo from './components/DoveSiamo/DoveSiamo';
import GallerySlider from './components/GallerySlider/GallerySlider';


function App() {
  const scrollToSection = (sectionId) => {
    const section = document.getElementById(sectionId);
    if (section) {
      section.scrollIntoView({ behavior: 'smooth' });
    }
  };

  return (
    <Router>
      <NavBar scrollToSection={scrollToSection} />
      <Routes>
        <Route path="/" element={
          <div>
            <div id="Landscape">
              <Hero/>
            </div>
            <div id="Agriturismo">
              <ImageScroller />
            </div>
            <div id="Gallery" className="container">
              <GallerySlider />
            </div>
            <div id="appartamenti">
              <Appartamenti/>
            </div>
            <div id="ContactUs">
              <ContactUs />
            </div>
          </div>
        } />
        <Route path="/dovesiamo" element={<DoveSiamo/>} />
      </Routes>
    </Router>
  );
}

export default App;
