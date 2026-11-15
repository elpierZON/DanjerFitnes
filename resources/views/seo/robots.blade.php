User-agent: *
Allow: /

# Áreas privadas: requieren sesión y no aportan nada en búsqueda
Disallow: /dashboard
Disallow: /trainer/
Disallow: /admin/
Disallow: /livewire/admin/
Disallow: /login
Disallow: /register
Disallow: /logout

# Endpoint interno de Livewire, no es contenido
Disallow: /livewire/message/

# El CSS y el JS deben poder rastrearse para que Google renderice la página
Allow: /css/
Allow: /js/
Allow: /images/

Sitemap: {{ $sitemap }}
