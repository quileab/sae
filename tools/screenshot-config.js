export default {
  baseUrl: 'https://sae.test',
  chromePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  outDir: 'C:\\qb\\qbweb\\sae\\public\\images\\manual',
  headless: true,
  debugLogs: false,
  viewport: { width: 1280, height: 800 },
  defaultWaitAfterLoad: 3000,

  // Autenticación inicial automática
  auth: {
    loginUrl: 'https://sae.test/login',
    username: 'quileab@gmail.com',
    password: 'Sadmin12345',
    usernameSelector: 'input[wire\\:model="email"]',
    passwordSelector: 'input[wire\\:model="password"]',
    waitAfterLoad: 2500,
    beforeSubmit: async (page, wait) => {
      // Tomamos la captura del login después de rellenar pero antes de enviar
      // Para login.png queremos que el correo se muestre anonimizado en la captura
      await page.evaluate(() => {
        const emailInput = document.querySelector('input[wire\\:model="email"]');
        if (emailInput) {
          emailInput.value = 'docente@sae.edu.ar';
          // Disparar evento para que Livewire/Alpine no lo sobrescriban inmediatamente
          emailInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
      await wait(1000);
      await page.screenshot({ path: 'C:\\qb\\qbweb\\sae\\public\\images\\manual\\login.png' });
      console.log('[Config] Captura login.png guardada.');

      // Restauramos el email real para poder loguearnos correctamente
      await page.evaluate(() => {
        const emailInput = document.querySelector('input[wire\\:model="email"]');
        if (emailInput) {
          emailInput.value = 'quileab@gmail.com';
          emailInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
      await wait(500);
    },
    submitSelector: 'button[type="submit"]',
    waitAfterSubmit: 6000
  },

  // Filtro de anonimización por defecto para todas las páginas
  defaultAnonymize: () => {
    const fakeNames = [
      "Gómez, Juan", "Rodríguez, María", "González, Carlos", "Fernández, Ana", 
      "López, Luis", "Martínez, Laura", "Sánchez, Pedro", "Pérez, Sofía", 
      "García, Diego", "Martín, Lucía", "Díaz, Javier", "Ruiz, Elena", 
      "Torres, Miguel", "Álvarez, Clara", "Romero, Tomás", "Sosa, Valentina",
      "Ortega, Facundo", "Flores, Camila", "Benítez, Lucas", "Medina, Martina"
    ];

    // 1. Reemplazar correos electrónicos en nodos de texto
    const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
    const walk = (node) => {
      if (node.nodeType === 3) {
        let text = node.nodeValue;
        if (emailRegex.test(text)) {
          node.nodeValue = text.replace(emailRegex, 'usuario@sae.edu.ar');
        }
      } else {
        for (let child of node.childNodes) {
          walk(child);
        }
      }
    };
    walk(document.body);

    // 2. Reemplazar nombre del docente logueado en la navegación
    const navUser = document.querySelector('.font-bold.truncate');
    if (navUser && navUser.textContent.includes('@')) {
      navUser.textContent = 'Docente SAE';
    }
  },

  steps: [
    // 1. Dashboard
    {
      name: 'dashboard',
      url: '/dashboard',
      waitAfterLoad: 3500
    },

    // 2. Asistencia General (PWA)
    {
      name: 'attendance',
      url: '/attendance',
      waitAfterLoad: 3000,
      actions: async (page, wait) => {
        // Seleccionamos la carrera "Gestión de las Organizaciones" (ID 400)
        console.log('[Step Actions] Seleccionando carrera ID 400...');
        await page.select('select[wire\\:model\\.live="careerId"]', '400');
        await wait(3000);

        // Desplegamos la tarjeta de la alumna con certificado médico (Gianela Villalba es la primera del listado mockeado)
        console.log('[Step Actions] Expandiendo fila de estudiante...');
        await page.click('.card .cursor-pointer');
        await wait(1500);
      },
      anonymize: () => {
        const fakeNames = [
          "Villalba, Gianela", "Gómez, Juan", "Rodríguez, María", "González, Carlos", 
          "Fernández, Ana", "López, Luis", "Martínez, Laura", "Sánchez, Pedro", "Pérez, Sofía"
        ];
        
        // Reemplazar nombres de estudiantes en el listado
        const pwaNames = document.querySelectorAll('.font-semibold.text-base');
        pwaNames.forEach((el, idx) => {
          if (idx === 0) {
            // Gianela Villalba tiene el certificado de prueba
            el.textContent = "Villalba, Gianela";
          } else {
            el.textContent = fakeNames[idx % fakeNames.length];
          }
        });

        // Reemplazar emails
        const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
        const walk = (node) => {
          if (node.nodeType === 3) {
            let text = node.nodeValue;
            if (emailRegex.test(text)) {
              node.nodeValue = text.replace(emailRegex, 'usuario@sae.edu.ar');
            }
          } else {
            for (let child of node.childNodes) {
              walk(child);
            }
          }
        };
        walk(document.body);
      }
    },

    // 3. Listado de Sesiones de Clase
    {
      name: 'class_sessions',
      url: '/class-sessions',
      waitAfterLoad: 3000
    },

    // 4. Asistencia por Sesión de Clase (Carga interna)
    {
      name: 'class_session_attendance',
      url: '/class-sessions/students/7870', // Sesión de Informática
      waitAfterLoad: 3500,
      anonymize: () => {
        const fakeNames = [
          "Gómez, Juan", "Rodríguez, María", "González, Carlos", "Fernández, Ana", 
          "López, Luis", "Martínez, Laura", "Sánchez, Pedro", "Pérez, Sofía", 
          "García, Diego", "Martín, Lucía"
        ];
        
        // Reemplazar nombres en las celdas de la tabla que contengan coma
        const tableRows = document.querySelectorAll('table tbody tr');
        tableRows.forEach((tr, idx) => {
          const tds = tr.querySelectorAll('td');
          tds.forEach(td => {
            if (td.textContent.includes(',') && !td.textContent.includes('»')) {
              td.textContent = fakeNames[idx % fakeNames.length];
            }
          });
        });
      }
    },

    // 5. Drawer lateral de la sesión de clase (Detalle notas/observaciones)
    {
      name: 'class_session_drawer',
      url: '/class-sessions/students/7870',
      waitAfterLoad: 3000,
      actions: async (page, wait) => {
        // Hacemos clic en el primer botón de edición del detalle (lápiz azul)
        console.log('[Step Actions] Abriendo drawer de estudiante...');
        const editButton = await page.$('.btn-ghost.text-primary');
        if (editButton) {
          await editButton.click();
        }
        await wait(1500); // Esperar que cargue y deslice el drawer
      },
      anonymize: () => {
        const fakeNames = [
          "Gómez, Juan", "Rodríguez, María", "González, Carlos", "Fernández, Ana", 
          "López, Luis", "Martínez, Laura", "Sánchez, Pedro", "Pérez, Sofía", 
          "García, Diego", "Martín, Lucía"
        ];
        
        // Reemplazar nombres en las celdas de la tabla
        const tableRows = document.querySelectorAll('table tbody tr');
        tableRows.forEach((tr, idx) => {
          const tds = tr.querySelectorAll('td');
          tds.forEach(td => {
            if (td.textContent.includes(',') && !td.textContent.includes('»')) {
              td.textContent = fakeNames[idx % fakeNames.length];
            }
          });
        });

        // Reemplazar el nombre en el título del Drawer
        const drawerHeader = document.querySelector('.drawer .mb-4');
        if (drawerHeader) {
          drawerHeader.childNodes.forEach(node => {
            if (node.nodeType === Node.TEXT_NODE && node.nodeValue.includes(',')) {
              node.nodeValue = ' Pérez, Sofía';
            }
          });
        }
      }
    },

    // 6. Contenidos de la Materia
    {
      name: 'contents',
      url: '/subjects-content/40106',
      waitAfterLoad: 3000
    },

    // 7. Calendario Escolar
    {
      name: 'calendar',
      url: '/calendar',
      waitAfterLoad: 3500
    },

    // 8. Mensajería / Chat
    {
      name: 'chat',
      url: '/chat',
      waitAfterLoad: 3000,
      actions: async (page, wait) => {
        // Hacemos clic en la primera conversación para cargar sus mensajes
        console.log('[Step Actions] Haciendo clic en la primera conversación del chat...');
        await page.evaluate(() => {
          const conv = document.querySelector('.absolute.inset-0.overflow-y-auto.p-2.space-y-2 .cursor-pointer') 
                       || document.querySelector('.cursor-pointer');
          if (conv) {
            conv.click();
          } else {
            console.log('No se encontró ninguna conversación para hacer clic.');
          }
        });
        await wait(2500); // Esperar que cargue la conversación
      },
      anonymize: () => {
        const fakeNames = [
          "Gómez, Juan", "Rodríguez, María", "González, Carlos", "Fernández, Ana", 
          "López, Luis", "Martínez, Laura", "Sánchez, Pedro", "Pérez, Sofía", 
          "García, Diego"
        ];
        
        const genericMessages = [
          "Hola, les recuerdo que la entrega del práctico es el próximo miércoles.",
          "Buenas tardes profe, ¿podría confirmarme si recibió el certificado médico?",
          "Sí, ya está registrado en el sistema de asistencia.",
          "Muchas gracias por la respuesta rápida.",
          "Estimados, recuerden revisar el material subido a la unidad 2 antes de la clase.",
          "Hola, quería saber la fecha del examen recuperatorio.",
          "La fecha del examen es el 12 de Junio.",
          "Excelente, gracias!"
        ];

        // Cambiar nombre del docente logueado en la cabecera del chat lateral
        const userHeader = document.querySelector('.shrink-0 .font-bold.truncate');
        if (userHeader) userHeader.textContent = 'Docente Autorizado';

        // Cambiar nombres de contactos de la lista izquierda
        const chatLabels = document.querySelectorAll('.cursor-pointer .font-bold.truncate');
        chatLabels.forEach((el, idx) => {
          let text = el.textContent.trim();
          if (text.includes('👨‍🎓') || text.includes('🧑‍🏫') || text.includes('👑') || text.includes('👔')) {
            const role = text.substring(0, 2);
            el.innerHTML = `${role} ${fakeNames[idx % fakeNames.length].split(',')[1]} ${fakeNames[idx % fakeNames.length].split(',')[0]}`;
          } else if (text.includes('📚')) {
            // Mantener chats grupales
          } else {
            el.textContent = `${fakeNames[idx % fakeNames.length].split(',')[1]} ${fakeNames[idx % fakeNames.length].split(',')[0]}`;
          }
        });

        // Cambiar remitentes de los mensajes individuales
        const senderNames = document.querySelectorAll('.text-xs.font-bold.opacity-70.mb-1');
        senderNames.forEach((el, idx) => {
          let text = el.textContent.trim();
          if (text.includes('Curso:')) {
            // Dejar intacto el indicador del curso
          } else {
            el.textContent = `${fakeNames[idx % fakeNames.length].split(',')[1]} ${fakeNames[idx % fakeNames.length].split(',')[0]}`;
          }
        });

        // Reemplazar textos de mensajes por mensajes genéricos institucionales
        const messageBodies = document.querySelectorAll('p.text-sm.whitespace-pre-wrap');
        messageBodies.forEach((el, idx) => {
          el.textContent = genericMessages[idx % genericMessages.length];
        });

        // Reemplazar título de la conversación activa arriba
        const activeChatHeader = document.querySelector('.w-full.lg\\:w-3\\/4.h-full.flex.flex-col > .shrink-0.bg-base-100 .font-bold.truncate');
        if (activeChatHeader) {
          let text = activeChatHeader.textContent.trim();
          if (text.includes('📚') || text.includes('Informática')) {
            // Mantener
          } else {
            activeChatHeader.textContent = 'Conversación Institucional';
          }
        }
      }
    }
  ]
};
