-- =====================================================================
-- SCRIPT DE VERIFICAÇÃO: Migração ano_lectivo → ano_lectivo_id
-- Data: 2026-01-25
-- Objectivo: Verificar integridade após migração completa
-- =====================================================================

-- =====================================================================
-- 1. VERIFICAR QUE TODOS OS REGISTOS TÊM ano_lectivo_id PREENCHIDO
-- =====================================================================

SELECT 'VERIFICAÇÃO 1: Registos com ano_lectivo_id NULL' AS verificacao;

SELECT
    'turmas' AS tabela,
    COUNT(*) AS registos_null
FROM turmas
WHERE ano_lectivo_id IS NULL

UNION ALL

SELECT
    'notas' AS tabela,
    COUNT(*) AS registos_null
FROM notas
WHERE ano_lectivo_id IS NULL

UNION ALL

SELECT
    'professor_disciplina_turma' AS tabela,
    COUNT(*) AS registos_null
FROM professor_disciplina_turma
WHERE ano_lectivo_id IS NULL

UNION ALL

SELECT
    'director_turma' AS tabela,
    COUNT(*) AS registos_null
FROM director_turma
WHERE ano_lectivo_id IS NULL

UNION ALL

SELECT
    'coordenador_curso' AS tabela,
    COUNT(*) AS registos_null
FROM coordenador_curso
WHERE ano_lectivo_id IS NULL;

-- Resultado esperado: Todas as linhas devem ter 0 registos_null


-- =====================================================================
-- 2. VERIFICAR SINCRONIZAÇÃO ano_lectivo ↔ ano_lectivo_id
-- =====================================================================

SELECT 'VERIFICAÇÃO 2: Sincronização ano_lectivo ↔ ano_lectivo_id' AS verificacao;

-- Turmas
SELECT
    'turmas' AS tabela,
    COUNT(*) AS registos_dessincronizados
FROM turmas t
LEFT JOIN anos_lectivos al ON t.ano_lectivo_id = al.id
WHERE t.ano_lectivo != al.nome

UNION ALL

-- Notas
SELECT
    'notas' AS tabela,
    COUNT(*) AS registos_dessincronizados
FROM notas n
LEFT JOIN anos_lectivos al ON n.ano_lectivo_id = al.id
WHERE n.ano_lectivo != al.nome

UNION ALL

-- Associações
SELECT
    'professor_disciplina_turma' AS tabela,
    COUNT(*) AS registos_dessincronizados
FROM professor_disciplina_turma pdt
LEFT JOIN anos_lectivos al ON pdt.ano_lectivo_id = al.id
WHERE pdt.ano_lectivo != al.nome

UNION ALL

-- Directores de Turma
SELECT
    'director_turma' AS tabela,
    COUNT(*) AS registos_dessincronizados
FROM director_turma dt
LEFT JOIN anos_lectivos al ON dt.ano_lectivo_id = al.id
WHERE dt.ano_lectivo != al.nome

UNION ALL

-- Coordenadores de Curso
SELECT
    'coordenador_curso' AS tabela,
    COUNT(*) AS registos_dessincronizados
FROM coordenador_curso cc
LEFT JOIN anos_lectivos al ON cc.ano_lectivo_id = al.id
WHERE cc.ano_lectivo != al.nome;

-- Resultado esperado: Todas as linhas devem ter 0 registos_dessincronizados


-- =====================================================================
-- 3. VERIFICAR INTEGRIDADE REFERENCIAL (Foreign Keys Órfãs)
-- =====================================================================

SELECT 'VERIFICAÇÃO 3: Foreign Keys órfãs (sem referência válida)' AS verificacao;

-- Turmas
SELECT
    'turmas' AS tabela,
    COUNT(*) AS fks_orfas
FROM turmas t
LEFT JOIN anos_lectivos al ON t.ano_lectivo_id = al.id
WHERE t.ano_lectivo_id IS NOT NULL AND al.id IS NULL

UNION ALL

-- Notas
SELECT
    'notas' AS tabela,
    COUNT(*) AS fks_orfas
FROM notas n
LEFT JOIN anos_lectivos al ON n.ano_lectivo_id = al.id
WHERE n.ano_lectivo_id IS NOT NULL AND al.id IS NULL

UNION ALL

-- Associações
SELECT
    'professor_disciplina_turma' AS tabela,
    COUNT(*) AS fks_orfas
FROM professor_disciplina_turma pdt
LEFT JOIN anos_lectivos al ON pdt.ano_lectivo_id = al.id
WHERE pdt.ano_lectivo_id IS NOT NULL AND al.id IS NULL

UNION ALL

-- Directores
SELECT
    'director_turma' AS tabela,
    COUNT(*) AS fks_orfas
FROM director_turma dt
LEFT JOIN anos_lectivos al ON dt.ano_lectivo_id = al.id
WHERE dt.ano_lectivo_id IS NOT NULL AND al.id IS NULL

UNION ALL

-- Coordenadores
SELECT
    'coordenador_curso' AS tabela,
    COUNT(*) AS fks_orfas
FROM coordenador_curso cc
LEFT JOIN anos_lectivos al ON cc.ano_lectivo_id = al.id
WHERE cc.ano_lectivo_id IS NOT NULL AND al.id IS NULL;

-- Resultado esperado: Todas as linhas devem ter 0 fks_orfas


-- =====================================================================
-- 4. VERIFICAR ANOS LECTIVOS NA TABELA CENTRALIZADA
-- =====================================================================

SELECT 'VERIFICAÇÃO 4: Anos lectivos na tabela centralizada' AS verificacao;

SELECT
    id,
    nome,
    estado AS actual,
    data_inicio,
    data_fim,
    created_at
FROM anos_lectivos
ORDER BY nome DESC;

-- Resultado esperado: Deve haver pelo menos 1 ano com estado = 1 (actual)


-- =====================================================================
-- 5. VERIFICAR ANO ACTUAL NA CONFIGURAÇÃO
-- =====================================================================

SELECT 'VERIFICAÇÃO 5: Ano lectivo actual na configuração' AS verificacao;

SELECT
    (SELECT valor FROM configuracoes WHERE chave = 'ano_lectivo_actual') AS config_ano,
    (SELECT nome FROM anos_lectivos WHERE estado = 1) AS tabela_ano;

-- Resultado esperado: Ambos os valores devem ser iguais (formato YYYY-YYYY)


-- =====================================================================
-- 6. ESTATÍSTICAS DE USO POR ANO LECTIVO
-- =====================================================================

SELECT 'VERIFICAÇÃO 6: Estatísticas de registos por ano lectivo' AS verificacao;

SELECT
    al.nome AS ano_lectivo,
    al.estado AS actual,
    (SELECT COUNT(*) FROM turmas WHERE ano_lectivo_id = al.id) AS turmas,
    (SELECT COUNT(*) FROM notas WHERE ano_lectivo_id = al.id) AS notas,
    (SELECT COUNT(*) FROM professor_disciplina_turma WHERE ano_lectivo_id = al.id) AS associacoes,
    (SELECT COUNT(*) FROM director_turma WHERE ano_lectivo_id = al.id) AS directores,
    (SELECT COUNT(*) FROM coordenador_curso WHERE ano_lectivo_id = al.id) AS coordenadores
FROM anos_lectivos al
ORDER BY al.nome DESC;


-- =====================================================================
-- 7. VERIFICAR PERFORMANCE - Uso de Índices
-- =====================================================================

SELECT 'VERIFICAÇÃO 7: Análise de performance (EXPLAIN)' AS verificacao;

-- Query usando FK (deve usar índice)
EXPLAIN SELECT * FROM notas WHERE ano_lectivo_id = 1;

-- Query usando string (NÃO deve usar índice - full table scan)
EXPLAIN SELECT * FROM notas WHERE ano_lectivo = '2025-2026';


-- =====================================================================
-- 8. VERIFICAR DADOS DE EXEMPLO
-- =====================================================================

SELECT 'VERIFICAÇÃO 8: Amostra de dados migrados' AS verificacao;

-- Turmas
SELECT
    'turmas' AS tabela,
    t.id,
    t.codigo,
    t.nome,
    t.ano_lectivo AS ano_string,
    al.nome AS ano_fk,
    CASE WHEN t.ano_lectivo = al.nome THEN 'OK' ELSE 'ERRO' END AS status
FROM turmas t
LEFT JOIN anos_lectivos al ON t.ano_lectivo_id = al.id
LIMIT 5;

-- Notas
SELECT
    'notas' AS tabela,
    n.id,
    n.aluno_id,
    n.disciplina_id,
    n.ano_lectivo AS ano_string,
    al.nome AS ano_fk,
    CASE WHEN n.ano_lectivo = al.nome THEN 'OK' ELSE 'ERRO' END AS status
FROM notas n
LEFT JOIN anos_lectivos al ON n.ano_lectivo_id = al.id
LIMIT 5;


-- =====================================================================
-- 9. RELATÓRIO FINAL
-- =====================================================================

SELECT 'RELATÓRIO FINAL: Resumo da Migração' AS verificacao;

SELECT
    (SELECT COUNT(*) FROM anos_lectivos) AS total_anos_lectivos,
    (SELECT COUNT(*) FROM anos_lectivos WHERE estado = 1) AS anos_activos,
    (SELECT COUNT(*) FROM turmas WHERE ano_lectivo_id IS NOT NULL) AS turmas_migradas,
    (SELECT COUNT(*) FROM notas WHERE ano_lectivo_id IS NOT NULL) AS notas_migradas,
    (SELECT COUNT(*) FROM professor_disciplina_turma WHERE ano_lectivo_id IS NOT NULL) AS associacoes_migradas,
    (SELECT COUNT(*) FROM director_turma WHERE ano_lectivo_id IS NOT NULL) AS directores_migrados,
    (SELECT COUNT(*) FROM coordenador_curso WHERE ano_lectivo_id IS NOT NULL) AS coordenadores_migrados;


-- =====================================================================
-- FIM DO SCRIPT DE VERIFICAÇÃO
-- =====================================================================
