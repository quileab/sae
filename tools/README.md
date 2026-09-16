# Utilidad de Captura de Pantallas con Puppeteer

Esta es una herramienta genérica y configurable diseñada para automatizar la captura de pantallas en aplicaciones web, permitiendo simular flujos de usuario (como inicio de sesión, navegación y clics) y aplicar scripts de anonimización en el navegador para ocultar información sensible antes de la captura.

## Requisitos
- **Node.js** (v18 o superior recomendado)
- **Puppeteer** o **Puppeteer-Core**
- Un navegador instalado compatible (como Google Chrome)

Si no tiene Puppeteer instalado en el proyecto destino, puede agregarlo ejecutando:
```bash
npm install puppeteer-core
```

## Estructura de Archivos
1. `screenshot-tool.js`: El motor ejecutor que lee la configuración, inicia el navegador, maneja la autenticación y ejecuta cada uno de los pasos definidos.
2. `screenshot-config.js`: Archivo de configuración del proyecto que define las credenciales, URL base y los pasos específicos de navegación y anonimización.

## Uso

Para ejecutar la herramienta, use el siguiente comando desde la terminal:
```bash
node tools/screenshot-tool.js [ruta/al/config.js]
```
*(Si no se especifica la ruta de configuración, la herramienta buscará por defecto un archivo llamado `./screenshot-config.js` en el directorio de ejecución).*

## Formato de Configuración (`screenshot-config.js`)

La estructura recomendada del archivo de configuración exporta un objeto por defecto:

```javascript
export default {
  baseUrl: 'https://mi-sitio.local',
  chromePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', // Ruta a Chrome
  outDir: './images/manual', // Carpeta donde se guardarán las imágenes
  headless: true, // Ejecutar en segundo plano
  viewport: { width: 1280, height: 800 },
  defaultWaitAfterLoad: 2000, // Tiempo de espera base tras cargar cada página

  // Opcional: Configuración del flujo de login inicial
  auth: {
    loginUrl: 'https://mi-sitio.local/login',
    username: 'admin@correo.com',
    password: 'Password123',
    usernameSelector: '#email-input',
    passwordSelector: '#password-input',
    submitSelector: '#btn-login-submit', // O 'submitAction' para acciones complejas
    waitAfterSubmit: 5000 // Espera para que finalice la navegación tras loguearse
  },

  // Opcional: Función de anonimización que se inyecta en cada paso
  defaultAnonymize: () => {
    // Código Javascript estándar que se ejecuta en el contexto del navegador.
    // Ejemplo: Reemplazar correos electrónicos
    const regex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
    document.body.innerHTML = document.body.innerHTML.replace(regex, 'usuario@anonimo.com');
  },

  // Listado secuencial de capturas a realizar
  steps: [
    {
      name: 'dashboard', // Nombre del archivo de salida (dashboard.png)
      url: '/dashboard', // Ruta relativa (se concatena con baseUrl)
      waitAfterLoad: 3000 // Espera extra para este paso en particular
    },
    {
      name: 'seccion_detalle',
      url: '/detalle/items',
      actions: async (page, wait) => {
        // Acciones interactivas personalizadas sobre la página usando Puppeteer
        await page.click('.btn-ver-mas');
        await wait(1000); // Esperar la transición/animación
      },
      anonymize: () => {
        // Sobrescribir la anonimización por defecto para este paso en particular
        document.querySelector('.nombre-cliente').textContent = 'Juan Pérez';
      }
    }
  ]
};
```
