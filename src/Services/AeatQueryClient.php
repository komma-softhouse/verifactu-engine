<?php

declare(strict_types=1);

namespace Komma\Verifactu\Services;

use DateTimeImmutable;
use Komma\Verifactu\Models\Records\FiscalIdentifier;
use SimpleXMLElement;
use UXML\UXML;

/**
 * Client for the AEAT query service (ConsultaFactuSistemaFacturacion),
 * complementing AeatClient (which only sends, never queries).
 *
 * REQUEST STRUCTURE: verified against the official XSD
 * (ConsultaLR.xsd, namespace .../tike/cont/ws/ConsultaLR.xsd), mirrored
 * from the AEAT-published schema at
 * https://sede.agenciatributaria.gob.es/static_files/Sede/Procedimiento_ayuda/G417/FicherosSuministros/V_1_1/ConsultaLR.xsd
 *
 * RESPONSE STRUCTURE: NOT verified field-by-field. The official response
 * schema (RespuestaConsultaLR.xsd) was only reachable as a binary/unparsed
 * fetch while building this class — every AEAT response in this codebase
 * (RespuestaSuministro.xsd) follows the same wrapper shape (an envelope
 * with the submission's Cabecera echoed back, a global result and a list
 * of per-record data), so `query()` returns the raw SimpleXMLElement
 * rather than a typed DTO. Complete the typed extraction once
 * RespuestaConsultaLR.xsd has been fetched and read directly — do not
 * guess field names.
 *
 * ENDPOINT: uses the same production/pre-production host pattern as
 * AeatClient (www1./prewww1.agenciatributaria.gob.es). Confirm the exact
 * path segment for this operation against SistemaFacturacion.wsdl
 * (https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/SistemaFacturacion.wsdl)
 * before the first production call — the constant below is the same
 * base path AeatClient uses for submission and has not been indepedently
 * confirmed to be identical for the query operation.
 */
class AeatQueryClient
{
    private const NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/ConsultaLR.xsd';

    private const PRODUCTION_ENDPOINT = 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion';

    private const SANDBOX_ENDPOINT = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion';

    private bool $isProduction = false;

    public function __construct(
        private readonly FiscalIdentifier $taxpayer,
        private readonly string $certificatePath,
        private readonly ?string $certificatePassphrase = null,
    ) {
    }

    public function setProduction(bool $isProduction = true): static
    {
        $this->isProduction = $isProduction;

        return $this;
    }

    /**
     * @param string $year e.g. "2026"
     * @param string $period two-digit month "01".."12", or the quarter code the AEAT
     *                       PeriodoImputacionType expects — confirm the exact enumerated
     *                       values against SuministroInformacion.xsd's PeriodoImputacionType
     *                       before relying on anything other than a plain month.
     * @param string|null $invoiceNumber NumSerieFactura (series+number), narrows to one invoice
     * @param string|null $paginationKey ClavePaginacion, to continue a previous paged query
     *
     * @return SimpleXMLElement the raw AEAT response — see the class docblock
     */
    public function query(
        string $year,
        string $period,
        ?string $invoiceNumber = null,
        ?string $paginationKey = null,
        bool $showIssuerName = false,
    ): SimpleXMLElement {
        $root = UXML::newInstance('sfLRC:ConsultaFactuSistemaFacturacion', null, [
            'xmlns:sfLRC' => self::NS,
        ]);

        $cabecera = $root->add('sf:Cabecera');
        $obligado = $cabecera->add('sf:ObligadoEmision');
        $obligado->add('sf:NombreRazon', $this->taxpayer->name);
        $obligado->add('sf:NIF', $this->taxpayer->nif);

        $filtro = $root->add('sfLRC:FiltroConsulta');
        $periodo = $filtro->add('sf:PeriodoImputacion');
        $periodo->add('sf:Ejercicio', $year);
        $periodo->add('sf:Periodo', $period);

        if ($invoiceNumber !== null) {
            $filtro->add('sf:NumSerieFactura', $invoiceNumber);
        }

        if ($paginationKey !== null) {
            $filtro->add('sf:ClavePaginacion', $paginationKey);
        }

        if ($showIssuerName) {
            $datosAdicionales = $root->add('sfLRC:DatosAdicionalesRespuesta');
            $datosAdicionales->add('sf:MostrarNombreRazonEmisor', 'S');
        }

        return $this->send($root->asXML());
    }

    private function send(string $bodyXml): SimpleXMLElement
    {
        $endpoint = $this->isProduction ? self::PRODUCTION_ENDPOINT : self::SANDBOX_ENDPOINT;

        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soapenv:Body>' . $bodyXml . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $context = stream_context_create([
            'ssl' => [
                'local_cert' => $this->certificatePath,
                'passphrase' => $this->certificatePassphrase,
                'verify_peer' => true,
            ],
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: text/xml; charset=UTF-8\r\nSOAPAction: \"\"",
                'content' => $envelope,
            ],
        ]);

        $response = file_get_contents($endpoint, false, $context);

        if ($response === false) {
            throw new \RuntimeException('AEAT query request failed (network/certificate error).');
        }

        $xml = simplexml_load_string($response);

        if ($xml === false) {
            throw new \RuntimeException('AEAT query response was not valid XML.');
        }

        return $xml;
    }
}
