import React, { useState } from 'react'; 
import { BrowserRouter as Router, Route, Routes } from 'react-router-dom';
import './App.css'; 
import NavBar from './components/NavBar/NavBar'; 
import ImageScroller from './components/ImageScroller/ScrollImage'; 
import ContactUs from './components/ContactUs/ContactUs'; 
import AuthPopup from './components/AuthPopup/AuthPopup'; 
import BookingSystem from './components/Booking/BookingSystem'; 
import Appartamenti from './components/Appartamenti/Appartamenti'; 
import Hero from './components/Hero/Hero';
import DoveSiamo from './components/DoveSiamo/DoveSiamo';
import GallerySlider from './components/GallerySlider/GallerySlider';


function App() {
  const [currentIndex, setCurrentIndex] = useState(0); 
  const [isBooking, setIsBooking] = useState(false); 
  const [isAuthPopupVisible, setAuthPopupVisible] = useState(false); 


  const toggleAuthPopup = () => {
    setAuthPopupVisible(!isAuthPopupVisible);
  };

  const handleBookingClick = () => {
    setIsBooking(true);
  };

  const handleNavigate = (sectionId) => {
    setIsBooking(false);
    scrollToSection(sectionId);
  };

  const scrollToSection = (sectionId) => {
    const section = document.getElementById(sectionId);
    if (section) {
      section.scrollIntoView({ behavior: 'smooth' });
    }
  };

  return (
    <>
      <Router>
        <NavBar onAuthClick={toggleAuthPopup} onBookingClick={handleBookingClick} scrollToSection={scrollToSection} />

        {!isBooking ? (
          <>
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
              <Route path="/prenota" element={<BookingSystem/>} />
            
            </Routes>
            {isAuthPopupVisible && (
              <AuthPopup togglePopup={toggleAuthPopup} />
            )}
          </>
        ) : (
          <BookingSystem onNavigate={handleNavigate} />
        )}
      </Router>   
    </>
  );
}

export default App;
