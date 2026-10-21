SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS demo_seed_controle (
    chave VARCHAR(100) PRIMARY KEY,
    executado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

START TRANSACTION;
INSERT IGNORE INTO demo_seed_controle (chave) VALUES ('previa_tcc_2026_10');
SET @executar_seed := ROW_COUNT();

INSERT INTO clientes (nome, telefone, email)
SELECT 'Mariana Costa', '(11) 99911-2201', 'mariana.costa@example.test'
WHERE @executar_seed = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'mariana.costa@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'Rafael Mendes', '(11) 99911-2202', 'rafael.mendes@example.test'
WHERE @executar_seed = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'rafael.mendes@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'Beatriz Oliveira', '(11) 99911-2203', 'beatriz.oliveira@example.test'
WHERE @executar_seed = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'beatriz.oliveira@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'Lucas Ferreira', '(11) 99911-2204', 'lucas.ferreira@example.test'
WHERE @executar_seed = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'lucas.ferreira@example.test');

SET @mariana_id := (SELECT id FROM clientes WHERE email = 'mariana.costa@example.test' ORDER BY id LIMIT 1);
SET @rafael_id := (SELECT id FROM clientes WHERE email = 'rafael.mendes@example.test' ORDER BY id LIMIT 1);
SET @beatriz_id := (SELECT id FROM clientes WHERE email = 'beatriz.oliveira@example.test' ORDER BY id LIMIT 1);
SET @lucas_id := (SELECT id FROM clientes WHERE email = 'lucas.ferreira@example.test' ORDER BY id LIMIT 1);

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @mariana_id, 'Apple', 'iPhone 13', 'DEMO-IMEI-0001', 'Tela trincada; toque funcionando parcialmente.'
WHERE @executar_seed = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0001');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @rafael_id, 'Samsung', 'Galaxy A54', 'DEMO-IMEI-0002', 'Conector de carga com mau contato.'
WHERE @executar_seed = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0002');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @beatriz_id, 'Motorola', 'Moto G84', 'DEMO-IMEI-0003', 'Aparelho não liga após queda.'
WHERE @executar_seed = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0003');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @lucas_id, 'Xiaomi', 'Redmi Note 12', 'DEMO-IMEI-0004', 'Bateria descarregando rapidamente.'
WHERE @executar_seed = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0004');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @mariana_id, 'Apple', 'iPad 9ª geração', 'DEMO-IMEI-0005', 'Conector de carga com falha.'
WHERE @executar_seed = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0005');

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @mariana_id, a.id, 'Concluído', 349.90, DATE_SUB(NOW(), INTERVAL 8 DAY)
FROM aparelhos a
WHERE @executar_seed = 1 AND a.imei = 'DEMO-IMEI-0001'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @rafael_id, a.id, 'Aguardando peça', 220.00, DATE_SUB(NOW(), INTERVAL 4 DAY)
FROM aparelhos a
WHERE @executar_seed = 1 AND a.imei = 'DEMO-IMEI-0002'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @beatriz_id, a.id, 'Em análise', 480.00, DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM aparelhos a
WHERE @executar_seed = 1 AND a.imei = 'DEMO-IMEI-0003'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @lucas_id, a.id, 'Recebido', 190.00, NOW()
FROM aparelhos a
WHERE @executar_seed = 1 AND a.imei = 'DEMO-IMEI-0004'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @mariana_id, a.id, 'Concluído', 159.90, DATE_SUB(NOW(), INTERVAL 12 DAY)
FROM aparelhos a
WHERE @executar_seed = 1 AND a.imei = 'DEMO-IMEI-0005'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO historico_status (ordem_id, status, descricao, data_hora)
SELECT os.id, os.status_os, 'Registro inicial de ordem demonstrativa.', os.data_entrada
FROM ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
WHERE @executar_seed = 1 AND a.imei LIKE 'DEMO-IMEI-%'
  AND NOT EXISTS (
      SELECT 1 FROM historico_status h
      WHERE h.ordem_id = os.id AND h.descricao = 'Registro inicial de ordem demonstrativa.'
  );

INSERT INTO pagamentos (ordem_id, cliente_id, valor_pago, forma_pagamento, data_pagamento, status_pagamento, observacao)
SELECT os.id, os.cliente_id, os.valor, 'Pix', DATE_SUB(NOW(), INTERVAL 6 DAY), 'Pago', 'Pagamento demonstrativo DEMO-IMEI-0001.'
FROM ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
WHERE @executar_seed = 1 AND a.imei = 'DEMO-IMEI-0001'
  AND NOT EXISTS (SELECT 1 FROM pagamentos WHERE observacao = 'Pagamento demonstrativo DEMO-IMEI-0001.');

INSERT INTO pagamentos (ordem_id, cliente_id, valor_pago, forma_pagamento, data_pagamento, status_pagamento, observacao)
SELECT os.id, os.cliente_id, 100.00, 'Cartão', DATE_SUB(NOW(), INTERVAL 3 DAY), 'Parcial', 'Pagamento parcial demonstrativo DEMO-IMEI-0002.'
FROM ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
WHERE @executar_seed = 1 AND a.imei = 'DEMO-IMEI-0002'
  AND NOT EXISTS (SELECT 1 FROM pagamentos WHERE observacao = 'Pagamento parcial demonstrativo DEMO-IMEI-0002.');

INSERT INTO pagamentos (ordem_id, cliente_id, valor_pago, forma_pagamento, data_pagamento, status_pagamento, observacao)
SELECT os.id, os.cliente_id, os.valor, 'Dinheiro', DATE_SUB(NOW(), INTERVAL 10 DAY), 'Pago', 'Pagamento demonstrativo DEMO-IMEI-0005.'
FROM ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
WHERE @executar_seed = 1 AND a.imei = 'DEMO-IMEI-0005'
  AND NOT EXISTS (SELECT 1 FROM pagamentos WHERE observacao = 'Pagamento demonstrativo DEMO-IMEI-0005.');

INSERT INTO despesas (descricao, categoria, valor, data_despesa, observacao)
SELECT 'Compra demonstrativa de insumos', 'Peça', 275.50, CURRENT_DATE, 'Despesa fictícia da prévia do TCC.'
WHERE @executar_seed = 1
  AND NOT EXISTS (SELECT 1 FROM despesas WHERE observacao = 'Despesa fictícia da prévia do TCC.');

INSERT INTO despesas (descricao, categoria, valor, data_despesa, observacao)
SELECT 'Material de embalagem', 'Material', 48.90, DATE_SUB(CURRENT_DATE, INTERVAL 3 DAY), 'Despesa fictícia da prévia do TCC.'
WHERE @executar_seed = 1
  AND NOT EXISTS (SELECT 1 FROM despesas WHERE descricao = 'Material de embalagem' AND observacao = 'Despesa fictícia da prévia do TCC.');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-PECA-001', 'Tela iPhone 13 compatível', 'Peça', 4, 185.00, 349.90, 'Item fictício de demonstração.'
WHERE @executar_seed = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-PECA-001');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-PECA-002', 'Conector de carga Galaxy A54', 'Peça', 2, 65.00, 220.00, 'Item fictício de demonstração.'
WHERE @executar_seed = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-PECA-002');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-ACESS-001', 'Cabo USB-C', 'Acessório', 12, 18.00, 39.90, 'Item fictício de demonstração.'
WHERE @executar_seed = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-ACESS-001');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-ACESS-002', 'Carregador USB-C 20W', 'Acessório', 7, 42.00, 79.90, 'Item fictício de demonstração.'
WHERE @executar_seed = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-ACESS-002');

INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao)
SELECT 'Mercado Livre', p.id, 1, 39.90, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Cliente demonstrativo', 'Venda fictícia de demonstração.'
FROM produtos p
WHERE @executar_seed = 1 AND p.codigo = 'DEMO-ACESS-001'
  AND NOT EXISTS (SELECT 1 FROM vendas_marketplace WHERE observacao = 'Venda fictícia de demonstração.');

UPDATE ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
SET os.status_os = CASE a.imei
    WHEN 'DEMO-IMEI-0001' THEN 'Concluído'
    WHEN 'DEMO-IMEI-0002' THEN 'Aguardando peça'
    WHEN 'DEMO-IMEI-0003' THEN 'Em análise'
    WHEN 'DEMO-IMEI-0004' THEN 'Recebido'
    WHEN 'DEMO-IMEI-0005' THEN 'Concluído'
END
WHERE a.imei IN ('DEMO-IMEI-0001', 'DEMO-IMEI-0002', 'DEMO-IMEI-0003', 'DEMO-IMEI-0004', 'DEMO-IMEI-0005');

UPDATE historico_status h
JOIN ordens_servico os ON os.id = h.ordem_id
JOIN aparelhos a ON a.id = os.aparelho_id
SET h.status = os.status_os
WHERE a.imei LIKE 'DEMO-IMEI-%'
  AND h.descricao = 'Registro inicial de ordem demonstrativa.';

UPDATE pagamentos
SET forma_pagamento = 'Cartão'
WHERE observacao = 'Pagamento parcial demonstrativo DEMO-IMEI-0002.';

UPDATE despesas
SET categoria = 'Peça'
WHERE descricao = 'Compra demonstrativa de insumos'
  AND observacao = 'Despesa fictícia da prévia do TCC.';

UPDATE produtos
SET categoria = 'Peça', nome = 'Tela iPhone 13 compatível'
WHERE codigo = 'DEMO-PECA-001';

UPDATE produtos
SET categoria = 'Peça'
WHERE codigo = 'DEMO-PECA-002';

COMMIT;

START TRANSACTION;
INSERT IGNORE INTO demo_seed_controle (chave) VALUES ('previa_tcc_2026_10_lote3');
SET @executar_lote3 := ROW_COUNT();

INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao)
SELECT s.marketplace, p.id, s.quantidade, s.valor_venda,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL s.meses_antes MONTH), INTERVAL s.dia DAY),
       s.cliente, CONCAT('Venda fictícia lote 3 - ', p.codigo, ' - ', s.meses_antes, ' meses atrás.')
FROM (
    SELECT 0 AS meses_antes, 3 AS dia, 'DEMO-ACESS-003' AS codigo, 1 AS quantidade, 19.90 AS valor_venda, 'Mercado Livre' AS marketplace, 'Cliente fictício 08' AS cliente
    UNION ALL SELECT 0, 12, 'DEMO-ACESS-004', 1, 69.90, 'Shopee', 'Cliente fictício 09'
    UNION ALL SELECT 1, 4, 'DEMO-ACESS-001', 1, 39.90, 'Mercado Livre', 'Cliente fictício 10'
    UNION ALL SELECT 1, 15, 'DEMO-ACESS-002', 1, 79.90, 'Loja física', 'Cliente fictício 11'
    UNION ALL SELECT 2, 5, 'DEMO-ACESS-003', 2, 19.90, 'Shopee', 'Cliente fictício 12'
    UNION ALL SELECT 2, 18, 'DEMO-ACESS-004', 1, 69.90, 'Mercado Livre', 'Cliente fictício 13'
    UNION ALL SELECT 3, 6, 'DEMO-ACESS-001', 1, 39.90, 'Shopee', 'Cliente fictício 14'
    UNION ALL SELECT 3, 19, 'DEMO-ACESS-002', 1, 79.90, 'Mercado Livre', 'Cliente fictício 15'
    UNION ALL SELECT 4, 7, 'DEMO-ACESS-003', 1, 19.90, 'Loja física', 'Cliente fictício 16'
    UNION ALL SELECT 4, 20, 'DEMO-ACESS-004', 1, 69.90, 'Shopee', 'Cliente fictício 17'
    UNION ALL SELECT 5, 8, 'DEMO-ACESS-001', 1, 39.90, 'Mercado Livre', 'Cliente fictício 18'
    UNION ALL SELECT 5, 21, 'DEMO-ACESS-002', 1, 79.90, 'Shopee', 'Cliente fictício 19'
    UNION ALL SELECT 6, 9, 'DEMO-ACESS-003', 1, 19.90, 'Mercado Livre', 'Cliente fictício 20'
    UNION ALL SELECT 6, 22, 'DEMO-ACESS-004', 1, 69.90, 'Loja física', 'Cliente fictício 21'
    UNION ALL SELECT 7, 10, 'DEMO-ACESS-001', 1, 39.90, 'Shopee', 'Cliente fictício 22'
    UNION ALL SELECT 7, 23, 'DEMO-ACESS-002', 1, 79.90, 'Mercado Livre', 'Cliente fictício 23'
    UNION ALL SELECT 8, 11, 'DEMO-ACESS-003', 1, 19.90, 'Loja física', 'Cliente fictício 24'
    UNION ALL SELECT 8, 24, 'DEMO-ACESS-004', 1, 69.90, 'Mercado Livre', 'Cliente fictício 25'
    UNION ALL SELECT 9, 12, 'DEMO-ACESS-001', 1, 39.90, 'Mercado Livre', 'Cliente fictício 26'
    UNION ALL SELECT 9, 25, 'DEMO-ACESS-002', 1, 79.90, 'Shopee', 'Cliente fictício 27'
) s
JOIN produtos p ON p.codigo = s.codigo
WHERE @executar_lote3 = 1;

UPDATE produtos p
JOIN (
    SELECT v.produto_id, SUM(v.quantidade) AS unidades_vendidas
    FROM vendas_marketplace v
    WHERE v.observacao LIKE 'Venda fictícia lote 3 - %'
    GROUP BY v.produto_id
) vendas ON vendas.produto_id = p.id
SET p.quantidade = GREATEST(p.quantidade - vendas.unidades_vendidas, 0)
WHERE @executar_lote3 = 1;

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT a.cliente_id, a.id, 'Concluído', s.valor,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL s.meses_antes MONTH), INTERVAL s.dia DAY)
FROM (
    SELECT 1 AS meses_antes, 6 AS dia, 'DEMO-IMEI-0001' AS imei, 275.00 AS valor
    UNION ALL SELECT 2, 8, 'DEMO-IMEI-0002', 185.00
    UNION ALL SELECT 3, 10, 'DEMO-IMEI-0003', 240.00
    UNION ALL SELECT 4, 12, 'DEMO-IMEI-0004', 165.00
    UNION ALL SELECT 5, 14, 'DEMO-IMEI-0005', 310.00
    UNION ALL SELECT 6, 16, 'DEMO-IMEI-0016', 495.00
    UNION ALL SELECT 7, 18, 'DEMO-IMEI-0017', 135.00
    UNION ALL SELECT 8, 20, 'DEMO-IMEI-0018', 155.00
    UNION ALL SELECT 9, 22, 'DEMO-IMEI-0011', 365.00
) s
JOIN aparelhos a ON a.imei = s.imei
WHERE @executar_lote3 = 1;

INSERT INTO historico_status (ordem_id, status, descricao, data_hora)
SELECT os.id, os.status_os, 'Reparo demonstrativo concluído em período histórico.', os.data_entrada
FROM ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
WHERE @executar_lote3 = 1
  AND a.imei LIKE 'DEMO-IMEI-%'
  AND os.status_os = 'Concluído'
  AND os.data_entrada < DATE_FORMAT(CURDATE(), '%Y-%m-01')
  AND NOT EXISTS (
      SELECT 1 FROM historico_status h
      WHERE h.ordem_id = os.id
        AND h.descricao = 'Reparo demonstrativo concluído em período histórico.'
  );

INSERT INTO pagamentos (ordem_id, cliente_id, valor_pago, forma_pagamento, data_pagamento, status_pagamento, observacao)
SELECT os.id, os.cliente_id, os.valor,
       CASE MOD(os.id, 3) WHEN 0 THEN 'Pix' WHEN 1 THEN 'Cartão' ELSE 'Dinheiro' END,
       os.data_entrada, 'Pago', CONCAT('Pagamento fictício da ordem histórica ', a.imei, '.')
FROM ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
JOIN historico_status h ON h.ordem_id = os.id
    AND h.descricao = 'Reparo demonstrativo concluído em período histórico.'
WHERE @executar_lote3 = 1
  AND NOT EXISTS (
      SELECT 1 FROM pagamentos p
      WHERE p.observacao = CONCAT('Pagamento fictício da ordem histórica ', a.imei, '.')
        AND p.data_pagamento = os.data_entrada
  );

INSERT INTO despesas (descricao, categoria, valor, data_despesa, observacao)
SELECT CONCAT(
           'Reposição demonstrativa - ',
           DATE_FORMAT(
               DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL s.meses_antes MONTH), INTERVAL s.dia DAY),
               '%m/%Y'
           )
       ),
       s.categoria, s.valor,
       DATE_ADD(DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL s.meses_antes MONTH), INTERVAL s.dia DAY),
       CONCAT('Despesa fictícia lote 3 - mês ', s.meses_antes, ' meses atrás.')
FROM (
    SELECT 0 AS meses_antes, 16 AS dia, 'Peça' AS categoria, 320.00 AS valor
    UNION ALL SELECT 1, 18, 'Frete', 68.50
    UNION ALL SELECT 2, 20, 'Peça', 245.00
    UNION ALL SELECT 3, 22, 'Mão de obra', 120.00
    UNION ALL SELECT 4, 12, 'Peça', 410.00
    UNION ALL SELECT 5, 14, 'Frete', 55.00
    UNION ALL SELECT 6, 16, 'Peça', 285.00
    UNION ALL SELECT 7, 18, 'Material', 92.00
    UNION ALL SELECT 8, 20, 'Peça', 360.00
    UNION ALL SELECT 9, 22, 'Frete', 74.90
) s
WHERE @executar_lote3 = 1
  AND NOT EXISTS (
      SELECT 1 FROM despesas x
      WHERE x.observacao = CONCAT('Despesa fictícia lote 3 - mês ', s.meses_antes, ' meses atrás.')
  );

COMMIT;

START TRANSACTION;
INSERT IGNORE INTO demo_seed_controle (chave) VALUES ('previa_tcc_2026_10_lote2');
SET @executar_lote2 := ROW_COUNT();

INSERT INTO clientes (nome, telefone, email)
SELECT 'Camila Rocha', '(11) 99911-2211', 'camila.rocha@example.test'
WHERE @executar_lote2 = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'camila.rocha@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'Pedro Almeida', '(11) 99911-2212', 'pedro.almeida@example.test'
WHERE @executar_lote2 = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'pedro.almeida@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'Julia Martins', '(11) 99911-2213', 'julia.martins@example.test'
WHERE @executar_lote2 = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'julia.martins@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'André Lima', '(11) 99911-2214', 'andre.lima@example.test'
WHERE @executar_lote2 = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'andre.lima@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'Fernanda Souza', '(11) 99911-2215', 'fernanda.souza@example.test'
WHERE @executar_lote2 = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'fernanda.souza@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'Diego Santos', '(11) 99911-2216', 'diego.santos@example.test'
WHERE @executar_lote2 = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'diego.santos@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'Larissa Gomes', '(11) 99911-2217', 'larissa.gomes@example.test'
WHERE @executar_lote2 = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'larissa.gomes@example.test');

INSERT INTO clientes (nome, telefone, email)
SELECT 'Thiago Ribeiro', '(11) 99911-2218', 'thiago.ribeiro@example.test'
WHERE @executar_lote2 = 1
  AND NOT EXISTS (SELECT 1 FROM clientes WHERE email = 'thiago.ribeiro@example.test');

SET @camila_id := (SELECT id FROM clientes WHERE email = 'camila.rocha@example.test' ORDER BY id LIMIT 1);
SET @pedro_id := (SELECT id FROM clientes WHERE email = 'pedro.almeida@example.test' ORDER BY id LIMIT 1);
SET @julia_id := (SELECT id FROM clientes WHERE email = 'julia.martins@example.test' ORDER BY id LIMIT 1);
SET @andre_id := (SELECT id FROM clientes WHERE email = 'andre.lima@example.test' ORDER BY id LIMIT 1);
SET @fernanda_id := (SELECT id FROM clientes WHERE email = 'fernanda.souza@example.test' ORDER BY id LIMIT 1);
SET @diego_id := (SELECT id FROM clientes WHERE email = 'diego.santos@example.test' ORDER BY id LIMIT 1);
SET @larissa_id := (SELECT id FROM clientes WHERE email = 'larissa.gomes@example.test' ORDER BY id LIMIT 1);
SET @thiago_id := (SELECT id FROM clientes WHERE email = 'thiago.ribeiro@example.test' ORDER BY id LIMIT 1);

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @camila_id, 'Apple', 'iPhone 12', 'DEMO-IMEI-0011', 'Aguardando chegada de tela OLED para concluir o reparo.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0011');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @pedro_id, 'Samsung', 'Galaxy S21 FE', 'DEMO-IMEI-0012', 'Aguardando conector de carga; aparelho não carrega.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0012');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @julia_id, 'Motorola', 'Moto G73', 'DEMO-IMEI-0013', 'Aguardando bateria compatível para substituição.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0013');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @andre_id, 'Xiaomi', 'Poco X5', 'DEMO-IMEI-0014', 'Desligando sozinho; diagnóstico em andamento.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0014');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @fernanda_id, 'Apple', 'iPhone 11', 'DEMO-IMEI-0015', 'Tela sem imagem; orçamento aprovado, aguardando início.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0015');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @diego_id, 'Samsung', 'Galaxy A34', 'DEMO-IMEI-0016', 'Substituição do módulo de tela concluída.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0016');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @larissa_id, 'Motorola', 'Edge 30 Neo', 'DEMO-IMEI-0017', 'Falha no microfone; reparo concluído e testado.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0017');

INSERT INTO aparelhos (cliente_id, marca, modelo, imei, defeito)
SELECT @thiago_id, 'Xiaomi', 'Redmi Note 11', 'DEMO-IMEI-0018', 'Aparelho recebido para avaliação da bateria.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM aparelhos WHERE imei = 'DEMO-IMEI-0018');

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @camila_id, a.id, 'Aguardando peça', 420.00, DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM aparelhos a
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0011'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @pedro_id, a.id, 'Aguardando peça', 185.00, DATE_SUB(NOW(), INTERVAL 6 DAY)
FROM aparelhos a
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0012'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @julia_id, a.id, 'Aguardando peça', 210.00, DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM aparelhos a
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0013'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @andre_id, a.id, 'Em análise', 320.00, DATE_SUB(NOW(), INTERVAL 5 DAY)
FROM aparelhos a
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0014'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @fernanda_id, a.id, 'Recebido', 390.00, DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM aparelhos a
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0015'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @diego_id, a.id, 'Concluído', 560.00, DATE_SUB(NOW(), INTERVAL 9 DAY)
FROM aparelhos a
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0016'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @larissa_id, a.id, 'Concluído', 145.00, DATE_SUB(NOW(), INTERVAL 14 DAY)
FROM aparelhos a
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0017'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO ordens_servico (cliente_id, aparelho_id, status_os, valor, data_entrada)
SELECT @thiago_id, a.id, 'Recebido', 175.00, NOW()
FROM aparelhos a
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0018'
  AND NOT EXISTS (SELECT 1 FROM ordens_servico WHERE aparelho_id = a.id);

INSERT INTO historico_status (ordem_id, status, descricao, data_hora)
SELECT os.id, os.status_os, 'Registro inicial da segunda etapa demonstrativa.', os.data_entrada
FROM ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
WHERE @executar_lote2 = 1 AND a.imei BETWEEN 'DEMO-IMEI-0011' AND 'DEMO-IMEI-0018'
  AND NOT EXISTS (
      SELECT 1 FROM historico_status h
      WHERE h.ordem_id = os.id AND h.descricao = 'Registro inicial da segunda etapa demonstrativa.'
  );

INSERT INTO pagamentos (ordem_id, cliente_id, valor_pago, forma_pagamento, data_pagamento, status_pagamento, observacao)
SELECT os.id, os.cliente_id, os.valor, 'Pix', DATE_SUB(NOW(), INTERVAL 8 DAY), 'Pago', 'Pagamento fictício da ordem DEMO-IMEI-0016.'
FROM ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0016'
  AND NOT EXISTS (SELECT 1 FROM pagamentos WHERE observacao = 'Pagamento fictício da ordem DEMO-IMEI-0016.');

INSERT INTO pagamentos (ordem_id, cliente_id, valor_pago, forma_pagamento, data_pagamento, status_pagamento, observacao)
SELECT os.id, os.cliente_id, os.valor, 'Cartão', DATE_SUB(NOW(), INTERVAL 13 DAY), 'Pago', 'Pagamento fictício da ordem DEMO-IMEI-0017.'
FROM ordens_servico os
JOIN aparelhos a ON a.id = os.aparelho_id
WHERE @executar_lote2 = 1 AND a.imei = 'DEMO-IMEI-0017'
  AND NOT EXISTS (SELECT 1 FROM pagamentos WHERE observacao = 'Pagamento fictício da ordem DEMO-IMEI-0017.');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-PECA-003', 'Tela OLED iPhone 12', 'Peça', 2, 230.00, 420.00, 'Item fictício; baixa quantidade para demonstração.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-PECA-003');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-PECA-004', 'Conector de carga Galaxy S21 FE', 'Peça', 1, 72.00, 185.00, 'Item fictício; baixa quantidade para demonstração.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-PECA-004');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-PECA-005', 'Bateria Moto G73', 'Peça', 3, 95.00, 210.00, 'Item fictício de demonstração.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-PECA-005');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-PECA-006', 'Módulo de tela Galaxy A34', 'Peça', 4, 310.00, 560.00, 'Item fictício de demonstração.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-PECA-006');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-PECA-007', 'Microfone Edge 30 Neo', 'Peça', 2, 55.00, 145.00, 'Item fictício de demonstração.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-PECA-007');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-PECA-008', 'Bateria Redmi Note 11', 'Peça', 6, 68.00, 175.00, 'Item fictício de demonstração.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-PECA-008');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-ACESS-003', 'Película de vidro 6.5 polegadas', 'Acessório', 18, 5.00, 19.90, 'Item fictício de demonstração.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-ACESS-003');

INSERT INTO produtos (codigo, nome, categoria, quantidade, valor_custo, valor_venda, observacao)
SELECT 'DEMO-ACESS-004', 'Fone Bluetooth básico', 'Acessório', 9, 35.00, 69.90, 'Item fictício de demonstração.'
WHERE @executar_lote2 = 1 AND NOT EXISTS (SELECT 1 FROM produtos WHERE codigo = 'DEMO-ACESS-004');

INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao)
SELECT 'Mercado Livre', p.id, 2, 19.90, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Cliente fictício 01', 'Venda fictícia lote 2 - DEMO-ACESS-003.'
FROM produtos p
WHERE @executar_lote2 = 1 AND p.codigo = 'DEMO-ACESS-003';

INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao)
SELECT 'Shopee', p.id, 1, 69.90, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Cliente fictício 02', 'Venda fictícia lote 2 - DEMO-ACESS-004.'
FROM produtos p
WHERE @executar_lote2 = 1 AND p.codigo = 'DEMO-ACESS-004';

INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao)
SELECT 'Mercado Livre', p.id, 1, 39.90, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Cliente fictício 03', 'Venda fictícia lote 2 - DEMO-ACESS-001.'
FROM produtos p
WHERE @executar_lote2 = 1 AND p.codigo = 'DEMO-ACESS-001';

INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao)
SELECT 'Shopee', p.id, 2, 19.90, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Cliente fictício 04', 'Venda fictícia lote 2 - DEMO-ACESS-003.'
FROM produtos p
WHERE @executar_lote2 = 1 AND p.codigo = 'DEMO-ACESS-003';

INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao)
SELECT 'Loja física', p.id, 1, 79.90, DATE_SUB(NOW(), INTERVAL 12 DAY), 'Cliente fictício 05', 'Venda fictícia lote 2 - DEMO-ACESS-002.'
FROM produtos p
WHERE @executar_lote2 = 1 AND p.codigo = 'DEMO-ACESS-002';

INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao)
SELECT 'Mercado Livre', p.id, 1, 69.90, DATE_SUB(NOW(), INTERVAL 16 DAY), 'Cliente fictício 06', 'Venda fictícia lote 2 - DEMO-ACESS-004.'
FROM produtos p
WHERE @executar_lote2 = 1 AND p.codigo = 'DEMO-ACESS-004';

INSERT INTO vendas_marketplace (marketplace, produto_id, quantidade, valor_venda, data_venda, cliente, observacao)
SELECT 'Shopee', p.id, 3, 19.90, DATE_SUB(NOW(), INTERVAL 21 DAY), 'Cliente fictício 07', 'Venda fictícia lote 2 - DEMO-ACESS-003.'
FROM produtos p
WHERE @executar_lote2 = 1 AND p.codigo = 'DEMO-ACESS-003';

UPDATE produtos p
JOIN (
    SELECT v.produto_id, SUM(v.quantidade) AS unidades_vendidas
    FROM vendas_marketplace v
    WHERE v.observacao LIKE 'Venda fictícia lote 2 - %'
    GROUP BY v.produto_id
) vendas ON vendas.produto_id = p.id
SET p.quantidade = GREATEST(
    p.quantidade - vendas.unidades_vendidas
        - CASE WHEN p.codigo = 'DEMO-ACESS-001' THEN 1 ELSE 0 END,
    0
)
WHERE @executar_lote2 = 1;

COMMIT;
