import puppeteer from 'puppeteer-core';
import fs from 'fs';
import path from 'path';

// Parse command line arguments
const args = process.argv.slice(2);
const configPathArg = args.find(arg => !arg.startsWith('--'));
const configPath = configPathArg || './screenshot-config.js';

console.log(`[Screenshot Tool] Cargar configuración desde: ${configPath}`);

let config;
try {
  const absolutePath = path.resolve(configPath);
  const module = await import(`file://${absolutePath}`);
  config = module.default;
} catch (error) {
  console.error(`[Screenshot Tool] Error al cargar el archivo de configuración: ${error.message}`);
  process.exit(1);
}

if (!config) {
  console.error('[Screenshot Tool] La configuración es nula o inválida.');
  process.exit(1);
}

(async () => {
  const chromePath = config.chromePath || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
  const outDir = config.outDir || './screenshots';
  const headless = config.headless !== false;
  const viewport = config.viewport || { width: 1280, height: 800 };
  const defaultWaitAfterLoad = config.defaultWaitAfterLoad !== undefined ? config.defaultWaitAfterLoad : 2000;

  console.log(`[Screenshot Tool] Creando directorio de salida: ${outDir}`);
  if (!fs.existsSync(outDir)) {
    fs.mkdirSync(outDir, { recursive: true });
  }

  console.log(`[Screenshot Tool] Iniciando Chrome en: ${chromePath} (headless: ${headless})`);
  const browser = await puppeteer.launch({
    executablePath: chromePath,
    headless: headless,
    defaultViewport: viewport,
    args: config.browserArgs || [
      '--ignore-certificate-errors',
      '--no-sandbox',
      '--disable-setuid-sandbox'
    ]
  });

  const page = await browser.newPage();

  if (config.debugLogs) {
    page.on('console', msg => console.log('[BROWSER LOG]:', msg.text()));
  }

  const wait = (ms) => new Promise(resolve => setTimeout(resolve, ms));

  try {
    // 1. Ejecutar Autenticación Inicial si existe
    if (config.auth) {
      console.log(`[Screenshot Tool] Navegando a la página de login: ${config.auth.loginUrl}`);
      await page.goto(config.auth.loginUrl, { waitUntil: 'domcontentloaded' });
      await wait(config.auth.waitAfterLoad || 2000);

      if (config.auth.usernameSelector && config.auth.username) {
        console.log(`[Screenshot Tool] Escribiendo usuario: ${config.auth.username}`);
        await page.type(config.auth.usernameSelector, config.auth.username);
      }

      if (config.auth.passwordSelector && config.auth.password) {
        console.log('[Screenshot Tool] Escribiendo contraseña...');
        await page.type(config.auth.passwordSelector, config.auth.password);
      }

      if (config.auth.beforeSubmit) {
        console.log('[Screenshot Tool] Ejecutando gancho antes de enviar formulario...');
        await config.auth.beforeSubmit(page, wait);
      }

      if (config.auth.submitSelector) {
        console.log(`[Screenshot Tool] Haciendo clic en el botón de login: ${config.auth.submitSelector}`);
        await page.click(config.auth.submitSelector);
      } else if (config.auth.submitAction) {
        console.log('[Screenshot Tool] Ejecutando acción personalizada para iniciar sesión...');
        await config.auth.submitAction(page, wait);
      }

      const waitTime = config.auth.waitAfterSubmit || 5000;
      console.log(`[Screenshot Tool] Esperando ${waitTime}ms para que finalice la autenticación...`);
      await wait(waitTime);
    }

    // 2. Procesar pasos
    for (const step of config.steps) {
      console.log(`\n========================================`);
      console.log(`[Screenshot Tool] Pasando al Paso: ${step.name}`);
      console.log(`========================================`);

      if (step.url) {
        const urlToNav = step.url.startsWith('http') ? step.url : `${config.baseUrl}${step.url}`;
        console.log(`[Screenshot Tool] Navegando a: ${urlToNav}`);
        await page.goto(urlToNav, { waitUntil: 'domcontentloaded' });
      }

      const stepWait = step.waitAfterLoad !== undefined ? step.waitAfterLoad : defaultWaitAfterLoad;
      if (stepWait > 0) {
        console.log(`[Screenshot Tool] Esperando ${stepWait}ms...`);
        await wait(stepWait);
      }

      if (step.actions) {
        console.log('[Screenshot Tool] Ejecutando acciones del paso...');
        await step.actions(page, wait);
      }

      // Anonimización del paso o general
      if (step.anonymize || config.defaultAnonymize) {
        console.log('[Screenshot Tool] Ejecutando script de anonimización...');
        const anonymizeFn = step.anonymize || config.defaultAnonymize;
        await page.evaluate(anonymizeFn);
      }

      if (step.waitBeforeCapture) {
        console.log(`[Screenshot Tool] Esperando ${step.waitBeforeCapture}ms antes de la captura...`);
        await wait(step.waitBeforeCapture);
      }

      const imgPath = path.join(outDir, `${step.name}.png`);
      const captureOpts = step.captureOptions || { fullPage: false };
      console.log(`[Screenshot Tool] Guardando captura en: ${imgPath}`);
      await page.screenshot({
        path: imgPath,
        ...captureOpts
      });
    }

    console.log('\n[Screenshot Tool] ¡Todas las capturas se generaron con éxito!');

  } catch (err) {
    console.error('[Screenshot Tool] Error crítico durante la ejecución:', err);
  } finally {
    console.log('[Screenshot Tool] Cerrando navegador...');
    await browser.close();
  }
})();
