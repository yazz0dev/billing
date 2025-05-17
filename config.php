<?php
// MongoDB connection configuration
// This constant is primarily for local development if .env or getenv('MONGODB_URI') is not used.
// On Vercel, getenv('MONGODB_URI') will be used.
if (!defined('MONGODB_URI_CONFIG')) {
    define('MONGODB_URI_CONFIG', 'mongodb+srv://yaseen:3GWpYAbQuZP06S6N@billcluster.rhevn0n.mongodb.net/?retryWrites=true&w=majority&appName=BillCluster');
}
?>