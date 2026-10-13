<?php

/**
 * SDK for AFIP Register Scope Ten (ws_sr_padron_a10)
 *
 * @link http://www.afip.gob.ar/ws/ws_sr_padron_a10/manual_ws_sr_padron_a10_v1.1.pdf WS Specification
 *
 * @author 	Afip SDK
 *
 * @version 1.0
 **/
class RegisterScopeTen extends AfipWebService
{
    public $soap_version = SOAP_1_1;

    public $WSDL = 'ws_sr_padron_a10-production.wsdl';

    public $URL = 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA10';

    public $WSDL_TEST = 'ws_sr_padron_a10.wsdl';

    public $URL_TEST = 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA10';

    /**
     * Asks to web service for servers status {@see WS
     * Specification item 3.1}
     *
     * @since 1.0
     *
     * @return object { appserver => Web Service status,
     *                dbserver => Database status, authserver => Autentication
     *                server status}
     **/
    public function GetServerStatus()
    {
        return $this->ExecuteRequest('dummy');
    }

    /**
     * Asks to web service for taxpayer details {@see WS
     * Specification item 3.2}
     *
     * @since 1.0
     *
     * @return object|null if taxpayer does not exists, return null,
     *                     if it exists, returns persona property of response {@see
     *                     WS Specification item 3.2.2}
     *
     * @throws Exception if exists an error in response
     **/
    public function GetTaxpayerDetails($identifier)
    {
        $ta = $this->afip->GetServiceTA('ws_sr_padron_a10');

        $params = [
            'token' => $ta->token,
            'sign' => $ta->sign,
            'cuitRepresentada' => $this->afip->CUIT,
            'idPersona' => $identifier,
        ];

        try {
            return $this->ExecuteRequest('getPersona', $params)->persona;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'No existe') !== false) {
                return null;
            } else {
                throw $e;
            }
        }
    }

    /**
     * Sends request to AFIP servers
     *
     * @since 1.0
     *
     * @param  string  $operation  SOAP operation to do
     * @param  array  $params  Parameters to send
     * @return mixed Operation results
     **/
    public function ExecuteRequest($operation, $params = [])
    {
        $results = parent::ExecuteRequest($operation, $params);

        return $results->{$operation == 'getPersona' ? 'personaReturn' : 'return'};
    }
}
