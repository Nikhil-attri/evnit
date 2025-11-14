// Service Worker for eVNIT PWA
const CACHE_NAME = 'evnit-cache-v1';
const STATIC_CACHE = 'evnit-static-v1';
const API_CACHE = 'evnit-api-v1';

// Files to cache for offline functionality
const STATIC_ASSETS = [
  '/',
  '/student-dashboard.html',
  '/driver-dashboard.html',
  '/admin-dashboard.html',
  '/login.html',
  '/manifest.json',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
  'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
  'https://unpkg.com/leaflet@1.9.4/images/marker-icon.png',
  'https://unpkg.com/leaflet@1.9.4/images/marker-shadow.png',
  'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
];

// Install event - cache static assets
self.addEventListener('install', event => {
  console.log('SW: Installing...');

  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then(cache => {
        console.log('SW: Caching static assets');
        return cache.addAll(STATIC_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('SW: Activating...');

  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== STATIC_CACHE && cacheName !== API_CACHE) {
            console.log('SW: Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  // Handle API requests with network-first strategy
  if (url.pathname.includes('/api/')) {
    event.respondWith(
      fetch(request)
        .then(response => {
          // Cache successful API responses for offline use
          if (response.ok) {
            const responseClone = response.clone();
            caches.open(API_CACHE)
              .then(cache => cache.put(request, responseClone))
              .catch(err => console.log('API cache error:', err));
          }
          return response;
        })
        .catch(() => {
          // Fall back to cache when offline
          return caches.match(request)
            .then(cachedResponse => {
              if (cachedResponse) {
                console.log('SW: Serving API from cache:', request.url);
                return cachedResponse;
              }
              // Return offline fallback for critical APIs
              return getOfflineFallback(request);
            });
        })
    );
    return;
  }

  // Handle static assets with cache-first strategy
  event.respondWith(
    caches.match(request)
      .then(cachedResponse => {
        if (cachedResponse) {
          console.log('SW: Serving from cache:', request.url);
          return cachedResponse;
        }

        // Fetch and cache new assets
        return fetch(request)
          .then(response => {
            if (response.ok && isCacheable(request)) {
              const responseClone = response.clone();
              caches.open(STATIC_CACHE)
                .then(cache => cache.put(request, responseClone))
                .catch(err => console.log('Cache error:', err));
            }
            return response;
          });
      })
  );
});

// Push notification event
self.addEventListener('push', event => {
  console.log('SW: Push notification received');

  const options = {
    body: event.data ? event.data.text() : 'New notification from eVNIT',
    icon: '/favicon.ico',
    badge: '/favicon.ico',
    vibrate: [200, 100, 200],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: 'Open App',
        icon: '/favicon.ico'
      },
      {
        action: 'close',
        title: 'Close',
        icon: '/favicon.ico'
      }
    ],
    requireInteraction: true,
    silent: false
  };

  event.waitUntil(
    self.registration.showNotification('eVNIT', options)
  );
});

// Notification click event
self.addEventListener('notificationclick', event => {
  console.log('SW: Notification clicked');

  event.notification.close();

  if (event.action === 'explore') {
    // Open the app to the relevant page
    event.waitUntil(
      clients.openWindow('/')
    );
  } else if (event.action === 'close') {
    // Just close the notification
    event.notification.close();
  } else {
    // Default action - open app
    event.waitUntil(
      clients.openWindow('/')
    );
  }
});

// Background sync for offline actions
self.addEventListener('sync', event => {
  console.log('SW: Background sync triggered', event.tag);

  if (event.tag === 'ride-request-sync') {
    event.waitUntil(syncRideRequests());
  } else if (event.tag === 'location-sync') {
    event.waitUntil(syncLocationData());
  }
});

// Helper functions
function isCacheable(request) {
  const url = new URL(request.url);

  // Don't cache API calls (handled separately)
  if (url.pathname.includes('/api/')) {
    return false;
  }

  // Only cache GET requests
  if (request.method !== 'GET') {
    return false;
  }

  // Cache static resources and map tiles
  return url.hostname.includes('openstreetmap.org') ||
         url.hostname.includes('unpkg.com') ||
         url.hostname === location.hostname;
}

function getOfflineFallback(request) {
  const url = new URL(request.url);

  // Offline fallbacks for different API endpoints
  if (url.pathname.includes('/locations/get_locations.php')) {
    return new Response(JSON.stringify({
      status: 'success',
      locations: getCachedCampusLocations()
    }), {
      headers: { 'Content-Type': 'application/json' }
    });
  }

  if (url.pathname.includes('/rides/get_ride_history.php')) {
    return new Response(JSON.stringify({
      status: 'success',
      rides: getCachedRideHistory()
    }), {
      headers: { 'Content-Type': 'application/json' }
    });
  }

  // Generic offline response
  return new Response(JSON.stringify({
    status: 'error',
    message: 'You are currently offline. Please check your connection.'
  }), {
    status: 503,
    headers: { 'Content-Type': 'application/json' }
  });
}

// Sync functions for background data
async function syncRideRequests() {
  try {
    const offlineRequests = await getOfflineData('rideRequests');

    for (const request of offlineRequests) {
      try {
        const response = await fetch('/api/rides/request_ride.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(request.data)
        });

        if (response.ok) {
          removeOfflineData('rideRequests', request.id);
        }
      } catch (error) {
        console.error('Sync failed for request:', request.id, error);
      }
    }
  } catch (error) {
    console.error('Sync ride requests error:', error);
  }
}

async function syncLocationData() {
  try {
    const pendingLocationUpdates = await getOfflineData('locationUpdates');

    for (const update of pendingLocationUpdates) {
      try {
        const response = await fetch('/api/vehicles/update_location.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(update.data)
        });

        if (response.ok) {
          removeOfflineData('locationUpdates', update.id);
        }
      } catch (error) {
        console.error('Location sync failed:', update.id, error);
      }
    }
  } catch (error) {
    console.error('Sync location data error:', error);
  }
}

// IndexedDB helpers for offline storage
function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('evnit-offline', 1);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);

    request.onupgradeneeded = () => {
      const db = request.result;

      // Create stores for different types of offline data
      if (!db.objectStoreNames.contains('rideRequests')) {
        db.createObjectStore('rideRequests', { keyPath: 'id' });
      }

      if (!db.objectStoreNames.contains('locationUpdates')) {
        db.createObjectStore('locationUpdates', { keyPath: 'id' });
      }

      if (!db.objectStoreNames.contains('campusData')) {
        db.createObjectStore('campusData', { keyPath: 'key' });
      }
    };
  });
}

async function getOfflineData(storeName) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(storeName, 'readonly');
    const store = transaction.objectStore(storeName);
    const request = store.getAll();

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function removeOfflineData(storeName, id) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(storeName, 'readwrite');
    const store = transaction.objectStore(storeName);
    const request = store.delete(id);

    request.onsuccess = () => resolve();
    request.onerror = () => reject(request.error);
  });
}

async function getCachedCampusLocations() {
  try {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction('campusData', 'readonly');
      const store = transaction.objectStore('campusData');
      const request = store.get('locations');

      request.onsuccess = () => {
        const result = request.result;
        resolve(result ? result.data : []);
      };
      request.onerror = () => reject(request.error);
    });
  } catch (error) {
    console.error('Get cached locations error:', error);
    return [];
  }
}

async function getCachedRideHistory() {
  try {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction('campusData', 'readonly');
      const store = transaction.objectStore('campusData');
      const request = store.get('rideHistory');

      request.onsuccess = () => {
        const result = request.result;
        resolve(result ? result.data : []);
      };
      request.onerror = () => reject(request.error);
    });
  } catch (error) {
    console.error('Get cached ride history error:', error);
    return [];
  }
}

// Message handling from main app
self.addEventListener('message', event => {
  if (event.data && event.data.type === 'CACHE_CAMPUS_DATA') {
    cacheCampusData(event.data.data);
  }
});

async function cacheCampusData(data) {
  try {
    const db = await openDB();
    const transaction = db.transaction('campusData', 'readwrite');
    const store = transaction.objectStore('campusData');

    // Cache locations
    if (data.locations) {
      store.put({ key: 'locations', data: data.locations });
    }

    // Cache ride history
    if (data.rideHistory) {
      store.put({ key: 'rideHistory', data: data.rideHistory });
    }

    // Cache user profile
    if (data.profile) {
      store.put({ key: 'profile', data: data.profile });
    }

    console.log('SW: Campus data cached successfully');
  } catch (error) {
    console.error('Cache campus data error:', error);
  }
}