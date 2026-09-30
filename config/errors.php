<?php

return [
    'pages' => [
        401 => ['title' => 'Necesitas iniciar sesión.', 'description' => 'Entra a tu cuenta para continuar. Si tu sesión terminó, vuelve a iniciarla.'],
        403 => ['title' => 'No tienes acceso a esta página.', 'description' => 'Tu cuenta no tiene permiso para realizar esta acción. Puedes seguir consultando la información pública.'],
        404 => ['title' => 'Esta página no está aquí.', 'description' => 'El enlace puede haber cambiado o la página ya no está disponible. Puedes volver al inicio o continuar en la base de datos.'],
        405 => ['title' => 'Esta acción no está disponible.', 'description' => 'La página no acepta esta solicitud. Utiliza los enlaces de la plataforma para continuar.'],
        410 => ['title' => 'Esta página ya no está disponible.', 'description' => 'Este contenido fue retirado. Puedes consultar la información disponible en la base de datos.'],
        419 => ['title' => 'Tu sesión ha caducado.', 'description' => 'Por seguridad, esta solicitud no pudo completarse. Abre de nuevo el formulario e inténtalo otra vez; revisa tus datos antes de enviarlos.'],
        429 => ['title' => 'Un momento, por favor.', 'description' => 'Recibimos varias solicitudes en poco tiempo. Espera un momento antes de volver a intentarlo.'],
        500 => ['title' => 'Algo no salió como esperábamos.', 'description' => 'Ocurrió un problema en la plataforma. Inténtalo más tarde. Si estabas enviando información, comprueba que se haya guardado antes de enviarla de nuevo.'],
        503 => ['title' => 'Volvemos en un momento.', 'description' => 'La plataforma no está disponible temporalmente. Por favor, vuelve a intentarlo más tarde.'],
    ],
    'fallbacks' => [
        '4xx' => ['title' => 'No pudimos completar tu solicitud.', 'description' => 'Revisa la información e inténtalo de nuevo, o vuelve al inicio para continuar.'],
        '5xx' => ['title' => 'Hay un problema temporal.', 'description' => 'No pudimos completar la solicitud en este momento. Por favor, vuelve a intentarlo más tarde.'],
    ],
];
