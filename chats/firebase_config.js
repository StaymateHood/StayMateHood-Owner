// Firebase Configuration for sand111 project
// IMPORTANT: Get actual values from Firebase Console
// Go to: https://console.firebase.google.com/project/sand111/settings/general
// Scroll to "Your apps" → Web app → Copy config

// const firebaseConfig = {
//   apiKey: "YOUR_WEB_API_KEY",  // Get from Firebase Console
//   authDomain: "sand111.firebaseapp.com",
//   databaseURL: "https://sand111-default-rtdb.firebaseio.com",  // Enable Realtime Database first
//   projectId: "sand111",
//   storageBucket: "sand111.appspot.com",
//   messagingSenderId: "YOUR_SENDER_ID",  // Get from Firebase Console
//   appId: "YOUR_APP_ID"  // Get from Firebase Console
// };


// const firebaseConfig = {
//   apiKey: "AIzaSyC-sTC9R3Tmh-KEdGQUQBWiyaIuu5oBlqk",
//   authDomain: "staymate-chat-test.firebaseapp.com",
//   databaseURL: "https://staymate-chat-test-default-rtdb.asia-southeast1.firebasedatabase.app",
//   projectId: "staymate-chat-test",
//   storageBucket: "staymate-chat-test.firebasestorage.app",
//   messagingSenderId: "954862025856",
//   appId: "1:954862025856:web:8e3f00eb04c6122dd33a65",
//   measurementId: "G-EJ1HG6ZJ14"
// };

       const firebaseConfig = {
                apiKey: "AIzaSyC75jVwyAQ_shMjLgZMYmROiJZlm6nUtEE",
                authDomain: "sand111.firebaseapp.com",
                databaseURL: "https://sand111.firebaseio.com",
                projectId: "sand111",
                storageBucket: "sand111.appspot.com",
                messagingSenderId: "470305805596",
                appId: "1:470305805596:web:66277e52804e129f8dd6d9",
                measurementId: "G-918Y9ME9CJ"
            };

// Initialize Firebase
import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
import { getDatabase } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-database.js';

const app = initializeApp(firebaseConfig);
const database = getDatabase(app);

export { database };
