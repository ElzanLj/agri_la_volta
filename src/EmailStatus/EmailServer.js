// Importazione delle librerie necessarie
import http from 'http';
import nodemailer from 'nodemailer';
import dotenv from 'dotenv';
dotenv.config();

// Funzione per impostare gli header CORS
// In produzione, imposta CORS_ORIGIN nel .env con l'URL reale del sito
// (es. https://www.agriturismolavolta.com), altrimenti resta aperto solo
// al server di sviluppo locale di Vite.
const ALLOWED_ORIGIN = process.env.CORS_ORIGIN || 'http://localhost:5173';

const setCorsHeaders = (res) => {
  res.setHeader('Access-Control-Allow-Origin', ALLOWED_ORIGIN);
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  res.setHeader('Access-Control-Allow-Credentials', 'true');
};

// Creazione del server
const server = http.createServer((req, res) => {
    // Imposta gli header CORS
    setCorsHeaders(res);
    
    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    if (req.method === 'POST' && req.url === '/send-email') {
        let body = '';
        req.on('data', chunk => {
            body += chunk.toString();
        });
        req.on('end', async () => {
            const contact = JSON.parse(body);

            // Creazione del transporter usando un vero servizio SMTP
            const transporter = nodemailer.createTransport({
                service: 'gmail',
                auth: {
                    user: process.env.GMAIL_USER,  // Il tuo indirizzo Gmail
                    pass: process.env.GMAIL_APP_PASS  // App password che hai generato
                }
            });

            const mailOptions = {
                from: process.env.GMAIL_USER,
                to: process.env.GMAIL_USER,
                replyTo: contact.email || undefined,
                subject: `Messaggio da ${contact.firstName} ${contact.lastName}`,
                text: `Nome: ${contact.firstName} ${contact.lastName}\nEmail: ${contact.email}\nTelefono: ${contact.phone}\nMotivo: ${contact.reason}\nMessaggio: ${contact.message}`
            };

            transporter.sendMail(mailOptions, (error, info) => {
                if (error) {
                    console.log(error);
                    res.writeHead(500, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ message: 'Errore nell\'invio dell\'email' }));
                } else {
                    console.log('Email inviata: ' + info.response);
                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ message: 'Email inviata con successo' }));
                }
            });
        });
    } else if (req.method === 'POST' && req.url === '/send-booking-confirmation') {
        let body = '';
        req.on('data', chunk => {
            body += chunk.toString();
        });
        req.on('end', async () => {
            const bookingDetails = JSON.parse(body);

            // Creazione del transporter usando un vero servizio SMTP
            const transporter = nodemailer.createTransport({
                service: 'gmail',
                auth: {
                    user: process.env.GMAIL_USER,  // Il tuo indirizzo Gmail
                    pass: process.env.GMAIL_APP_PASS  // App password che hai generato
                }
            });

            const mailOptions = {
                from: process.env.GMAIL_USER,
                to: process.env.GMAIL_USER, // notifica al gestore della struttura
                replyTo: bookingDetails.email || undefined,
                subject: `Nuova prenotazione: ${bookingDetails.apartmentName} (${bookingDetails.nome || ''} ${bookingDetails.cognome || ''})`,
                text: `Nuova prenotazione ricevuta!

Appartamento: ${bookingDetails.apartmentName}
Check-in: ${bookingDetails.checkIn}
Check-out: ${bookingDetails.checkOut}
Adulti: ${bookingDetails.adults}
Bambini: ${bookingDetails.children}

Dati del cliente:
Nome: ${bookingDetails.nome || '-'} ${bookingDetails.cognome || ''}
Email: ${bookingDetails.email || '-'}
Telefono: ${bookingDetails.telefono || '-'}`
            };

            transporter.sendMail(mailOptions, (error, info) => {
                if (error) {
                    console.log(error);
                    res.writeHead(500, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ message: 'Errore nell\'invio dell\'email' }));
                } else {
                    console.log('Email di conferma inviata: ' + info.response);
                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ message: 'Email di conferma inviata con successo' }));
                }
            });
        });
    } else {
        res.writeHead(404, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ message: 'Endpoint non trovato' }));
    }
});

// Imposta la porta su cui il server ascolta
const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
    console.log(`Server in ascolto sulla porta ${PORT}`);
});
