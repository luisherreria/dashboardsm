<?php
/**
 * Genera el manual de uso en PDF.
 * Uso: php public/manual/generar.php
 */

require dirname(dirname(__DIR__)) . '/lib/fpdf/fpdf.php';

class ManualPdf extends FPDF
{
    public function t($texto)
    {
        $texto = (string) $texto;
        if (function_exists('iconv')) {
            $convertido = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
            if ($convertido !== false) {
                return $convertido;
            }
        }
        return utf8_decode($texto);
    }

    public function Header()
    {
        if ($this->PageNo() === 1) {
            return;
        }
        $this->SetFillColor(13, 71, 161);
        $this->Rect(0, 0, 210, 14, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetXY(14, 4);
        $this->Cell(120, 6, $this->t('COMEDICA  ·  Manual de uso'), 0, 0, 'L');
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(62, 6, $this->t('Sistema de Gestión Médica'), 0, 0, 'R');
        $this->SetXY(14, 20);
    }

    public function Footer()
    {
        $this->SetY(-14);
        $this->SetDrawColor(203, 213, 225);
        $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(90, 6, $this->t('Uso interno  ·  COMEDICA'), 0, 0, 'L');
        $this->Cell(92, 6, $this->t('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    public function seccion($numero, $titulo)
    {
        if ($this->GetY() > 250) {
            $this->AddPage();
        } else {
            $this->Ln(3);
        }
        $this->SetFillColor(21, 101, 192);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(0, 8, $this->t('  ' . $numero . '    ' . $titulo), 0, 1, 'L', true);
        $this->Ln(2);
        $this->SetTextColor(30, 41, 59);
    }

    public function sub($titulo)
    {
        if ($this->GetY() > 260) {
            $this->AddPage();
        }
        $this->Ln(1);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(13, 71, 161);
        $this->Cell(0, 6, $this->t($titulo), 0, 1, 'L');
        $this->SetTextColor(51, 65, 85);
    }

    public function cuerpo($texto)
    {
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(51, 65, 85);
        $this->MultiCell(0, 5, $this->t($texto));
        $this->Ln(1);
    }

    public function paso($texto)
    {
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(51, 65, 85);
        $x = $this->GetX();
        $this->SetX(18);
        $this->MultiCell(174, 5, $this->t('-  ' . $texto));
        $this->SetX($x);
    }

    public function pantalla($archivo)
    {
        $ruta = __DIR__ . '/img/' . $archivo;
        if (!is_file($ruta)) {
            $this->cuerpo('No se encontró la captura ' . $archivo . '.');
            return;
        }
        $info = getimagesize($ruta);
        if (!$info) {
            return;
        }
        $this->Ln(1);
        $maxAlto = 262 - $this->GetY();
        if ($maxAlto < 55) {
            $this->AddPage();
            $maxAlto = 230;
        }
        $ancho = 182;
        $alto = $ancho * ($info[1] / $info[0]);
        if ($alto > $maxAlto) {
            $alto = $maxAlto;
            $ancho = $alto * ($info[0] / $info[1]);
        }
        $x = 14 + (182 - $ancho) / 2;
        $this->SetDrawColor(203, 213, 225);
        $this->Image($ruta, $x, $this->GetY(), $ancho, $alto);
        $this->Rect($x, $this->GetY(), $ancho, $alto);
        $this->Ln($alto + 3);
    }
}

$pdf = new ManualPdf('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetMargins(14, 18, 14);
$pdf->AddPage();

$pdf->SetFillColor(13, 71, 161);
$pdf->Rect(0, 0, 210, 78, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Helvetica', '', 11);
$pdf->SetXY(14, 22);
$pdf->Cell(0, 6, $pdf->t('SISTEMA DE GESTIÓN MÉDICA'), 0, 1, 'L');
$pdf->SetFont('Helvetica', 'B', 26);
$pdf->SetX(14);
$pdf->Cell(0, 12, 'COMEDICA', 0, 1, 'L');
$pdf->SetFont('Helvetica', '', 14);
$pdf->SetX(14);
$pdf->Cell(0, 8, $pdf->t('Manual de uso'), 0, 1, 'L');
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetX(14);
$pdf->SetTextColor(219, 234, 254);
$pdf->Cell(0, 6, $pdf->t('Del ingreso al sistema hasta el último punto del menú'), 0, 1, 'L');

$pdf->SetY(90);
$pdf->SetTextColor(30, 41, 59);
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->Cell(0, 7, $pdf->t('Qué contiene este manual'), 0, 1);
$pdf->cuerpo('Una guía corta para operar el tablero. Cada capítulo sigue el orden del Menú principal: primero cómo entrar, después cada módulo y, al final, cómo salir.');
$pdf->paso('Ingreso al sistema.');
$pdf->paso('Barra superior y menú.');
$pdf->paso('Evolución de Padrones.');
$pdf->paso('Mapa de cobertura.');
$pdf->paso('Auditoría de Consumo Prestacional.');
$pdf->paso('Control de sectores: órdenes, facturación y auditoría de facturas.');
$pdf->paso('Administradores, visible solo para el rol administrador.');
$pdf->paso('Cerrar sesión.');
$pdf->Ln(2);
$pdf->cuerpo('Después de entrar, la primera pantalla es Evolución de Padrones. El resto de los módulos se abre desde Menú principal, arriba a la derecha.');

$pdf->seccion('1', 'Ingreso al sistema');
$pdf->cuerpo('Esta es la primera pantalla. Pide usuario o correo y contraseña. El ojo junto a la clave la muestra o la oculta. Ingresar al Sistema abre el tablero. Si los datos no coinciden, o la cuenta está bloqueada, avisa y no entra. Al ingresar bien se abre Evolución de Padrones.');
$pdf->pantalla('01-login.png');

$pdf->seccion('2', 'Barra superior y menú');
$pdf->cuerpo('Arriba figuran el nombre, el estado En línea y Menú principal. Ese botón abre el panel de la derecha, en el orden de los módulos. La cruz, o un clic fuera, lo cierra. Cerrar sesión está al final y pide confirmación.');
$pdf->pantalla('03-menu.png');

$pdf->seccion('3', 'Evolución de Padrones');
$pdf->cuerpo('Pantalla de inicio. Los accesos rápidos abren cada módulo y Personalizar elige cuáles se ven. En Filtros se marca la obra social y hasta 8 años, y Filtrar / Actualizar llena los indicadores. Evolución temporal compara los años. Demografía y Planes y geografía usan el último corte: edad, sexo, condiciones, planes, localidades y zonas.');
$pdf->pantalla('02-evolucion.png');

$pdf->seccion('4', 'Mapa de cobertura');
$pdf->cuerpo('Abre vacío. Al elegir la obra social se dibujan los afiliados. Ver sucursales agrega los prestadores, con un triángulo de color por categoría. Al pasar el mouse se leen nombre, número, teléfono y dirección. Buscar acerca el mapa a ese punto.');
$pdf->pantalla('04-mapa.png');

$pdf->seccion('5', 'Auditoría de Consumo Prestacional');
$pdf->cuerpo('Se elige consumo por afiliado o por prestador, la obra y el período (aa/mm, máximo 12 meses) y se pulsa Auditar. El primer cuadro es el ranking; cada clic abre dónde se consumió y después el detalle por fecha. Abajo están la ficha del afiliado y los avisos al prestador o al afiliado.');
$pdf->pantalla('05-auditoria.png');

$pdf->seccion('6', 'Control de sectores');
$pdf->cuerpo('Las tres pantallas usan Desde, Hasta y Consultar. El rango máximo es de 12 meses. Un clic en una cantidad abre el detalle. Excel y PDF bajan el cuadro.');
$pdf->sub('6.1  Órdenes y autorizaciones');
$pdf->cuerpo('A la izquierda, órdenes cargadas por día en ambulatorio, sanatorial y medicamentos. A la derecha, autorizadas y rechazadas. Análisis IA resume el cuadro.');
$pdf->pantalla('06-ordenes.png');
$pdf->sub('6.2  Carga de facturación');
$pdf->cuerpo('Registros que cargó cada persona de data entry, y el mismo trabajo día por día: promedio, registros por hora y minutos por registro.');
$pdf->pantalla('07-facturacion.png');
$pdf->sub('6.3  Auditoría de facturas');
$pdf->cuerpo('Misma elección de fechas. El lugar ya está en el menú; el conteo de facturas se conecta en el paso siguiente.');
$pdf->pantalla('08-facturas.png');

$pdf->seccion('7', 'Administradores');
$pdf->cuerpo('Solo lo ve el rol administrador. Lista usuario, nombre, correo, rol y si la cuenta está activa o bloqueada. Sirve para consultar. El alta y la edición no se hacen en esta pantalla.');
$pdf->pantalla('09-administradores.png');

$pdf->seccion('8', 'Cerrar sesión');
$pdf->paso('Abra Menú principal.');
$pdf->paso('Pulse Cerrar sesión, al final del panel.');
$pdf->paso('Confirme Sí, salir. Cancelar deja la sesión abierta.');
$pdf->cuerpo('Al salir se vuelve a la pantalla de ingreso. Si la sesión vence sola, el sistema también pide entrar de nuevo antes de mostrar datos.');

$pdf->Ln(4);
$pdf->SetFillColor(241, 245, 249);
$pdf->SetFont('Helvetica', 'I', 9);
$pdf->SetTextColor(71, 85, 105);
$pdf->MultiCell(0, 5, $pdf->t('  Consejo: si un cuadro queda en blanco, revise que haya una obra social o un rango de fechas elegido y vuelva a pulsar el botón de consulta de esa pantalla.'), 0, 'L', true);

$destino = __DIR__ . '/Manual_de_uso_COMEDICA.pdf';
$pdf->Output('F', $destino);
echo $destino . PHP_EOL;
