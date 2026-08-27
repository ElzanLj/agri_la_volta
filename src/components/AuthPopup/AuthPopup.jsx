// AuthPopup.jsx

import React, { useState } from 'react'; // Importa React e il hook useState
import { auth } from '../NavBar/firebaseConfig'; // Importa la configurazione Firebase
import { 
    signInWithEmailAndPassword, 
    signInWithPopup, 
    GoogleAuthProvider, 
    createUserWithEmailAndPassword, 
    signOut 
} from 'firebase/auth'; // Importa le funzioni di autenticazione di Firebase
import './AuthPopup.css'; // Importa il file CSS per lo stile del componente

const AuthPopup = ({ onClose }) => {
    // Stato locale per email, password, messaggi di errore e modalità di registrazione/accesso
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [isRegistering, setIsRegistering] = useState(false);

    // Funzione per l'accesso tramite email e password
    const handleEmailSignIn = () => {
        signInWithEmailAndPassword(auth, email, password)
            .then((userCredential) => {
                const user = userCredential.user;
                console.log('User signed in:', user);
                onClose(); // Chiude il popup dopo l'accesso
            })
            .catch((error) => {
                setError(error.message); // Mostra il messaggio di errore
                console.error('Error during sign-in:', error);
            });
    };

    // Funzione per l'accesso tramite Google
    const handleGoogleSignIn = () => {
        const provider = new GoogleAuthProvider();
        signInWithPopup(auth, provider)
            .then((result) => {
                console.log('User signed in with Google:', result.user);
                onClose(); // Chiude il popup dopo l'accesso
            })
            .catch((error) => {
                console.error('Error during sign-in with Google:', error);
            });
    };

    // Funzione per la registrazione di un nuovo utente
    const handleSignUp = () => {
        createUserWithEmailAndPassword(auth, email, password)
            .then((userCredential) => {
                const user = userCredential.user;
                console.log('User signed up:', user);
                onClose(); // Chiude il popup dopo la registrazione
            })
            .catch((error) => {
                setError(error.message); // Mostra il messaggio di errore
                console.error('Error during sign-up:', error);
            });
    };

    return (
        <div className="auth-popup-overlay">
            <div className="auth-popup">
                <button className="close-btn" onClick={onClose}>✖</button>
                <h2>{isRegistering ? 'Registrati' : 'Accedi'}</h2>
                {error && <p className="error-msg">{error}</p>}
                <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="Email"
                    className="auth-input"
                />
                <input
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Password"
                    className="auth-input"
                />
                {isRegistering ? (
                    <>
                        <input
                            type="text"
                            placeholder="Nome"
                            className="auth-input"
                        />
                        <input
                            type="text"
                            placeholder="Cognome"
                            className="auth-input"
                        />
                        <button className="auth-btn" onClick={handleSignUp}>Registrati</button>
                        <p>Hai già un account? <span className="switch-mode" onClick={() => setIsRegistering(false)}>Accedi</span></p>
                    </>
                ) : (
                    <>
                        <button className="auth-btn" onClick={handleEmailSignIn}>Accedi con Email</button>
                        <button className="auth-btn google" onClick={handleGoogleSignIn}>Accedi con Google</button>
                        <p>Non hai un account? <span className="switch-mode" onClick={() => setIsRegistering(true)}>Registrati</span></p>
                    </>
                )}
            </div>
        </div>
    );
};

export default AuthPopup;
