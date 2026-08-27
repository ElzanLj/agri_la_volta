import { initializeApp } from "firebase/app";
import { getAnalytics, isSupported as isAnalyticsSupported } from "firebase/analytics";
import { getAuth } from "firebase/auth";
import { getFirestore } from "firebase/firestore";

// Le chiavi vengono lette dalle variabili d'ambiente (vedi .env.example).
// La apiKey di Firebase per il web NON è un segreto in sé (è pensata per
// essere pubblica), ma va comunque protetta con le Regole di Sicurezza di
// Firestore/Auth lato console Firebase: sono quelle regole a decidere chi
// può davvero leggere/scrivere i dati, non questa chiave.
export const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId: import.meta.env.VITE_FIREBASE_APP_ID,
  measurementId: import.meta.env.VITE_FIREBASE_MEASUREMENT_ID,
};

if (!firebaseConfig.apiKey) {
  console.error(
    "Configurazione Firebase mancante: crea un file .env nella root del progetto partendo da .env.example"
  );
}

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);

// getAnalytics richiede un browser reale e fallisce in alcuni ambienti
// (SSR, test, browser senza supporto): la inizializziamo solo se supportata.
let analytics = null;
isAnalyticsSupported().then((supported) => {
  if (supported) analytics = getAnalytics(app);
});

export { auth, db, analytics };
