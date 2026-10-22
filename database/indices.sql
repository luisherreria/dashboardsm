-- Indices de las tablas masivas de vfpmedicos.
-- Volver a ejecutarlos si se recrea o se carga de nuevo la tabla.
-- En el WHERE no envolver OBRASOC, PERIODO ni COAFILIADO con TRIM() ni UPPER().
-- Normalizar el valor en PHP antes del bind.

-- Padrón histórico (unos 3 millones de filas).
-- Ya existe en este servidor como idx_obrasoc_periodo (OBRASOC, PERIODO).
ALTER TABLE t_padron ADD INDEX idx_obrasoc_periodo (OBRASOC, PERIODO);

-- Ficha y consumos del afiliado. La columna física es COAFILIADO.
-- Ya existe en este servidor como idx_afiliado (COAFILIADO).
ALTER TABLE detconsu ADD INDEX idx_afiliado (COAFILIADO);
