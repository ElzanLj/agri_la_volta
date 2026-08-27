import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";
import { getAuth } from "firebase/auth";
import { getFirestore } from "firebase/firestore";

export const firebaseConfig = {
  apiKey: "AIzaSyDHhAqIrswB9WKVLne8dYNVk2KJbDKuPz8",
  authDomain: "agriturismolavolta-f58e9.firebaseapp.com",
  projectId: "agriturismolavolta-f58e9",
  storageBucket: "agriturismolavolta-f58e9.appspot.com",
  messagingSenderId: "555735381100",
  appId: "1:555735381100:web:ff02532650b90f60f79449",
  measurementId: "G-YL4DJXK5QR"
};

const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);
const auth = getAuth(app);
const db = getFirestore(app);

export { auth, db, analytics };