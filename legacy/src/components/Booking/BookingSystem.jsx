import React, { useState, useEffect, useRef } from 'react';
import { db } from '../NavBar/firebaseConfig';
import { collection, doc, setDoc, getDocs, updateDoc, arrayUnion } from 'firebase/firestore';
import './booking-system.css';
import Pagamenti from '../Pagamenti/Pagamenti';

import backgroundImage from '../../assets/fotoDintorni/appartamentoFoto.jpg';
import mimosa from '../../assets/fotoAppartamenti/appartamento8.jpg';
import girasole from '../../assets/fotoAppartamenti/appartamento10.jpg';
import rosa from '../../assets/fotoAppartamenti/appartamento6.jpg';
import ciclamino from '../../assets/fotoDintorni/appartamentoFoto.jpg';
import margherita from '../../assets/fotoAppartamenti/appartamento8.jpg';
import viola from '../../assets/fotoAppartamenti/agri1.jpg';


const imageMap = {
  mimosa,
  girasole,
  margherita,
  ciclamino,
  viola,
  rosa
};

const scrollToRef = (ref) => {
  if (ref && ref.current) {
    ref.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
};

const BookingSystem = ({ onNavigate }) => {
  const [checkIn, setCheckIn] = useState('');
  const [checkOut, setCheckOut] = useState('');
  const [adults, setAdults] = useState(0);
  const [children, setChildren] = useState(0);
  const [availableApartments, setAvailableApartments] = useState([]);
  const [selectedApartment, setSelectedApartment] = useState(null);
  const [totalCost, setTotalCost] = useState(0);

  const availableApartmentsRef = useRef(null);
  const bookingConfirmationRef = useRef(null);

  useEffect(() => {
    const createDatabase = async () => {
      const apartmentsRef = collection(db, "apartments");
      const apartmentsSnapshot = await getDocs(apartmentsRef);

      if (apartmentsSnapshot.empty) {
        const apartments = [
          { id: "Mimosa", name: "Appartamento Mimosa", description: "Accogliente appartamento con portico e vista su giardino riservato", price: 80, image: "mimosa" },
          { id: "Girasole", name: "Appartamento Girasole", description: "Spazioso appartamento con terrazza privata", price: 90, image: "girasole" },
          { id: "Margherita", name: "Appartamento Margherita", description: "Spazioso appartamento con terrazza privata e vista panoramica, ideale per famiglie o gruppi di amici", price: 100, image: "margherita" },
          { id: "Ciclamino", name: "Appartamento Ciclamino", description: " Appartamento per coppie o piccole famiglie, con cortile e portico riservato", price: 80, image: "ciclamino" },
          { id: "Viola", name: "Appartamento Viola", description: "Romantico appartamento ideale per coppie, comprende un terrazzo con vista panoramica", price: 90, image: "viola" },
          { id: "Rosa", name: "Appartamento Rosa", description: "Appartamento familiare con ampio soggiorno, portico privato e sottopalco", price: 100, image: "rosa" },
        ];

        apartments.forEach((apartment) => {
          const apartmentDocRef = doc(apartmentsRef, apartment.id);
          setDoc(apartmentDocRef, {
            name: apartment.name,
            description: apartment.description,
            price: apartment.price,
            image: apartment.image,
            bookings: [],
          });
        });
      } else {
        console.log("Database already exists, skipping creation");
      }
    };

    createDatabase();
  }, []);

  const handleCheckInChange = (date) => {
    setCheckIn(date);
  };

  const handleCheckOutChange = (date) => {
    setCheckOut(date);
  };


  const handleAdultsChange = (value) => {
    const newAdults = Math.max(0, value); // Impedisce numeri negativi
    const totalPeople = newAdults + children;
    if (totalPeople <= 6) {
      setAdults(newAdults);
    } else {
      alert("Il numero massimo di persone consentito è 6.");
      setAdults(Math.max(0, 6 - children)); // Imposta il massimo numero di adulti possibile
    }
  };

  const handleChildrenChange = (value) => {
    const newChildren = Math.max(0, value); // Impedisce numeri negativi
    const totalPeople = adults + newChildren;
    if (totalPeople <= 6) {
      setChildren(newChildren);
    } else {
      alert("Il numero massimo di persone consentito è 6.");
      setChildren(Math.max(0, 6 - adults)); // Imposta il massimo numero di bambini possibile
    }
  };

  const handlePeopleChange = (type, value) => {
    const newValue = Math.max(0, Math.min(6, parseInt(value) || 0));
    const otherValue = type === 'adults' ? children : adults;
    const totalPeople = newValue + otherValue;

    if (totalPeople <= 6) {
      if (type === 'adults') {
        setAdults(newValue);
      } else {
        setChildren(newValue);
      }
    } else {
      alert("Il numero massimo di persone consentito è 6.");
      if (type === 'adults') {
        setAdults(Math.max(0, 6 - children));
      } else {
        setChildren(Math.max(0, 6 - adults));
      }
    }
  };

  const calculateTotalCost = (apartmentPrice) => {
    const nights = calculateNights(checkIn, checkOut);
    const totalPeople = adults + children;
    let extraAdults = totalPeople > 2 ? adults - 2 : 0;
    let extraChildren = 0;
  
    if (adults === 1) {
      // Se c'è un solo adulto, il primo bambino non paga
      extraChildren = children > 1 ? children - 1 : 0;
    } else {
      // Altrimenti, tutti i bambini pagano se superano la capacità
      extraChildren = totalPeople > 2 ? children : 0;
    }
  
    const adultsCost = extraAdults * 20;
    const childrenCost = extraChildren * 10;
    const cleaningFee = 30;
    return (apartmentPrice + adultsCost + childrenCost) * nights + cleaningFee;
  };

  const handleSearchApartments = async () => {
    if (!checkIn || !checkOut) {
      alert("Per favore, seleziona sia la data di check-in che di check-out.");
      return;
    }

    const apartmentsRef = collection(db, "apartments");
    const apartmentsSnapshot = await getDocs(apartmentsRef);
    const availableApartments = [];

    apartmentsSnapshot.forEach((apartmentDoc) => {
      const apartmentData = apartmentDoc.data();
      const bookings = apartmentData.bookings;
      const isAvailable = !bookings.some((booking) => {
        return (
          (booking.checkIn <= checkIn && booking.checkOut >= checkIn) ||
          (booking.checkIn <= checkOut && booking.checkOut >= checkOut) ||
          (booking.checkIn >= checkIn && booking.checkOut <= checkOut)
        );
      });

      if (isAvailable) {
        availableApartments.push({
          id: apartmentDoc.id,
          name: apartmentData.name,
          description: apartmentData.description,
          price: apartmentData.price,
          image: apartmentData.image,
        });
      }
    });

    setAvailableApartments(availableApartments);
    setTimeout(() => scrollToRef(availableApartmentsRef), 100);
  };

  const calculateNights = (checkIn, checkOut) => {
    const start = new Date(checkIn);
    const end = new Date(checkOut);
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays;
  };

  const handleSelectApartment = (apartmentId) => {
    const selected = availableApartments.find((apartment) => apartment.id === apartmentId);
    const total = calculateTotalCost(selected.price);
    setSelectedApartment(apartmentId);
    setTotalCost(total);
    setTimeout(() => scrollToRef(bookingConfirmationRef), 100);
  };

  const sendConfirmationEmail = async (bookingDetails) => {
    try {
      const response = await fetch('http://localhost:3001/send-booking-confirmation', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          email: 'cliente@example.com', // Sostituisci con l'email del cliente se la conosci
          apartmentName: bookingDetails.apartmentName,
          checkIn: bookingDetails.checkIn,
          checkOut: bookingDetails.checkOut,
          adults: bookingDetails.adults,
          children: bookingDetails.children
        }),
      });
  
      const data = await response.json();
      if (response.ok) {
        console.log(data.message);
      } else {
        console.error('Errore:', data.message);
      }
    } catch (error) {
      console.error('Errore:', error);
    }
  };
  
  const handleConfirmBooking = async () => {
    if (selectedApartment) {
      try {
        const apartmentRef = doc(collection(db, "apartments"), selectedApartment);
        const booking = {
          checkIn: checkIn,
          checkOut: checkOut,
          adults: adults,
          children: children,
        };
  
        await updateDoc(apartmentRef, {
          bookings: arrayUnion(booking),
        });
  
        // Dettagli della prenotazione da inviare via email
        const apartment = availableApartments.find(a => a.id === selectedApartment);
        const bookingDetails = {
          apartmentName: apartment.name,
          checkIn,
          checkOut,
          adults,
          children
        };
  
        // Invia l'email di conferma
        await sendConfirmationEmail(bookingDetails);
  
        alert(`Prenotazione confermata per l'appartamento ${selectedApartment}!`);
        // Reset form after successful booking
        setCheckIn('');
        setCheckOut('');
        setAdults(0);
        setChildren(0);
        setSelectedApartment(null);
        setAvailableApartments([]);
        setTotalCost(0);
      } catch (error) {
        console.error("Errore durante la conferma della prenotazione:", error);
        alert("Si è verificato un errore durante la conferma della prenotazione. Riprova più tardi.");
      }
    }
  };

  const Header = () => {
    return (
      <div className="header" style={{ backgroundImage: `linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url(${backgroundImage})` }}>
        <div className="header-content">
          <h1>PRENOTA UN APPARTAMENTO</h1>
          <p>Inserisci le tue date e trova l'appartamento più adatto</p>
        </div>
      </div>
    );
  };

  return (
    <div className="booking-system">
      <Header />

      <div className='booking-form'>
        <h2>Seleziona una data di check-in e check-out</h2>
        <div className="form-group">
          <label htmlFor="check-in">Check-in:</label>
          <input id="check-in" type="date" value={checkIn} onChange={(e) => handleCheckInChange(e.target.value)} />
        </div>
        <div className="form-group">
          <label htmlFor="check-out">Check-out:</label>
          <input id="check-out" type="date" value={checkOut} onChange={(e) => handleCheckOutChange(e.target.value)} />
        </div>
        <h2>Seleziona il numero di adulti e bambini</h2>
        <div className="form-group">
          <label htmlFor="adults">Adulti:</label>
          <input 
            id="adults" 
            type="number" 
            value={adults} 
            onChange={(e) => handlePeopleChange('adults', e.target.value)}
            onBlur={() => handlePeopleChange('adults', adults)}
            min="0"
            max="6"
          />
        </div>
        <div className="form-group">
          <label htmlFor="children">Bambini:</label>
          <input 
            id="children" 
            type="number" 
            value={children} 
            onChange={(e) => handlePeopleChange('children', e.target.value)}
            onBlur={() => handlePeopleChange('children', children)}
            min="0"
            max="6"
          />
        </div>
        <button className="search-button" onClick={handleSearchApartments}>Cerca appartamenti disponibili</button>
      </div>
      
      {availableApartments.length > 0 && (
        <div className="available-apartments" ref={availableApartmentsRef}>
          <h3>Appartamenti disponibili</h3>
          <div className="apartment-grid">
            {availableApartments.map((apartment) => (
              <div key={apartment.id} className="apartment-card">
                <img src={imageMap[apartment.image]} alt={apartment.name} />
                <div className="apartment-info">
                  <h4>{apartment.name}</h4>
                  <p className="description">{apartment.description}</p>
                  <p className="price">Prezzo per notte: €{apartment.price}</p>
                  <button className="select-button" onClick={() => handleSelectApartment(apartment.id)}>Seleziona</button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {selectedApartment && (
        <div className="booking-confirmation" ref={bookingConfirmationRef}>
          <h2>Conferma la tua prenotazione</h2>
          <p>Hai selezionato l'appartamento: <strong>{availableApartments.find(a => a.id === selectedApartment).name}</strong></p>
          <p><strong>Date:</strong> {checkIn} - {checkOut}</p>
          <div className="payment-details">
            <p><strong>Dettagli di Pagamento:</strong></p>
            <p>Prezzo base: €{availableApartments.find(a => a.id === selectedApartment).price} x {calculateNights(checkIn, checkOut)} notti = €{availableApartments.find(a => a.id === selectedApartment).price * calculateNights(checkIn, checkOut)}</p>
            {adults > 2 && <p>Costo per adulti extra: €20 x {adults - 2} adulti x {calculateNights(checkIn, checkOut)} notti = €{(adults - 2) * 20 * calculateNights(checkIn, checkOut)}</p>}
            {adults === 1 && children > 1 && <p>Costo per bambini extra: €10 x {children - 1} bambini x {calculateNights(checkIn, checkOut)} notti = €{(children - 1) * 10 * calculateNights(checkIn, checkOut)}</p>}
            {adults > 1 && children > 0 && (adults + children) > 2 && <p>Costo per bambini: €10 x {children} bambini x {calculateNights(checkIn, checkOut)} notti = €{children * 10 * calculateNights(checkIn, checkOut)}</p>}
            <p>Tassa di pulizia: €30</p>
            <p><strong>Totale: €{totalCost}</strong></p>
          </div>

          <div className='payment'>
            <Pagamenti></Pagamenti>

          </div>

          <button className="confirm-button" onClick={handleConfirmBooking}>Conferma prenotazione</button>
        </div>
      )}
    </div>
  );
};

export default BookingSystem;