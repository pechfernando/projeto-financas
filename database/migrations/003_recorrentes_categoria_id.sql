SET NAMES utf8mb4;

ALTER TABLE lancamentos_recorrentes
    ADD COLUMN categoria_id INT UNSIGNED NULL AFTER tipo,
    ADD CONSTRAINT fk_recorrentes_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL;
