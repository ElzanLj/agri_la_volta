import React, { useState, useEffect } from 'react';
import { Link as RouterLink } from 'react-router-dom';
import './Navbar.css';

import homeLogo from '../../assets/icon/homeLogo.png';
import galleryLogo from '../../assets/icon/galleryLogo.png';
import whereLogo from '../../assets/icon/whereLogo.png';
import contactLogo from '../../assets/icon/contactLogo.png';
import appartamentLogo from '../../assets/icon/appartmentLogo.png';
import drago from '../../assets/fotoGenerali/logo1.webp';
import hotelLogo from '../../assets/icon/HotelLogo.png';

// Funzione per scrollare verso l'alto della pagina Dove Siamo
const scrollToTop = () => {
  window.scrollTo({
    top: 0,
    behavior: 'smooth'
  });
};

const NavBar = ({ scrollToSection }) => {
  const [showSolidNavbar, setShowSolidNavbar] = useState(false);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      setShowSolidNavbar(window.scrollY > 50);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const toggleMobileMenu = () => {
    setIsMobileMenuOpen(!isMobileMenuOpen);
  };

  // Funzione per chiudere il menu mobile
  const closeMobileMenu = () => {
    setIsMobileMenuOpen(false);
  };

  // Funzione combinata per gestire il click su un elemento del menu
  const handleNavItemClick = (sectionName) => {
    scrollToSection(sectionName);
    closeMobileMenu();
  };

  // Componente per ogni elemento del menu di navigazione
  const NavItem = ({ logo, text, to, onClick, sectionName }) => (
    <li className="nav-item">
      <RouterLink 
        to={to} 
        className='nav-link' 
        onClick={() => {
          if (onClick) {
            onClick();
          } else if (sectionName) {
            handleNavItemClick(sectionName);
          }
          closeMobileMenu();
        }}
      >
        {logo && <img src={logo} className="nav-logo" alt={text} />}
        <span className="nav-text">{text}</span>
      </RouterLink>
    </li>
  );

  return (
    <nav className={`nav ${showSolidNavbar ? 'dark-nav' : ''}`}> 
      <div className="logo">
        <img src={drago} className="agri-logo" alt="Logo" />
      </div>
      <div className="navbar-content">
        <ul className={`nav-links ${isMobileMenuOpen ? 'mobile' : ''}`}>
          <NavItem logo={homeLogo} text="Home" to="/" sectionName="Landscape" />
          <NavItem logo={appartamentLogo} text="Agriturismo" to="/" sectionName="Agriturismo" />
          <NavItem logo={galleryLogo} text="Gallery" to="/" sectionName="Gallery" />
          <NavItem logo={hotelLogo} text="Appartamenti" to="/" sectionName="appartamenti" />
          <NavItem logo={whereLogo} text="Dove Siamo" to="/dovesiamo" onClick={() => { scrollToTop(); closeMobileMenu(); }} />
          <NavItem logo={contactLogo} text="Contattaci" to="/" sectionName="ContactUs" />
        </ul>

        <div className="mobile-menu-icon" onClick={toggleMobileMenu}>
          &#9776; {/* hamburger icon */}
        </div>
      </div>
    </nav>
  );
}

export default NavBar;