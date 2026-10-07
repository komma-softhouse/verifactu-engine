<?php
namespace Komma\Verifactu\Models\Records;

/**
 * Factura emitida por un tercero o por el destinatario
 *
 * @field EmitidaPorTerceroODestinatario
 */
enum IssuedBy: string {
    /** Emitida por un tercero en nombre del obligado (requiere el bloque Tercero) */
    case ThirdParty = 'T';

    /** Emitida por el destinatario (autofacturación) */
    case Recipient = 'D';
}
