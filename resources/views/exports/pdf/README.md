# Template PDF - Facilita

Sistema de templates padronizados para geração de PDFs com formatação profissional e consistente.

## 📐 Estrutura

```
exports/pdf/
├── partials/
│   ├── styles.blade.php    # Estilos CSS compartilhados
│   ├── header.blade.php    # Cabeçalho padrão
│   └── footer.blade.php    # Rodapé padrão
├── mini-pauta.blade.php    # ✅ Atualizado
├── lista-alunos.blade.php  # ✅ Atualizado
└── ... (outros PDFs)
```

## 🎨 Como Usar os Templates

### 1. Incluir Estilos Padrão

No `<head>` do seu PDF:

```blade
<head>
    <meta charset="UTF-8">
    <title>Título do Documento</title>
    @include('exports.pdf.partials.styles')
</head>
```

### 2. Incluir Cabeçalho Padrão

Logo após o `<body>`:

```blade
<body>
    <div class="container">
        @include('exports.pdf.partials.header', ['instituicao' => $instituicao])

        <!-- Seu conteúdo aqui -->
    </div>
</body>
```

### 3. Incluir Rodapé Padrão

Antes de fechar `</div>` do container:

```blade
        @include('exports.pdf.partials.footer', [
            'instituicao' => $instituicao,
            'gerado_em' => $gerado_em,
            'assinaturas' => ['O Professor', 'O Director de Turma']
        ])
    </div>
</body>
```

## 📋 Classes CSS Disponíveis

### Containers
- `.container` - Container principal
- `.info-box` - Caixa de informações (fundo cinza claro, bordas)
- `.table-wrapper` - Wrapper para tabelas (com margens)

### Título
- `.titulo-documento` - Caixa de título centralizada com fundo

### Informações
- `.info-row` - Linha de informação (2 células)
- `.info-cell` - Célula de informação
- `.info-label` - Label (negrito, largura fixa)
- `.info-value` - Valor da informação

### Tabelas
- `.data-table` - Tabela de dados
- `.numero` / `.col-ordem` - Coluna de número (fundo cinza)
- `.nome-aluno` / `.col-nome` - Coluna de nome (alinhado à esquerda)
- `.nota-positiva` - Nota >= 10 (verde/preto)
- `.nota-negativa` - Nota < 10 (vermelho)
- `.nota-destaque` - Nota com destaque (MT, etc)

### Estatísticas
- `.estatisticas-box` - Caixa de estatísticas
- `.estatisticas-titulo` - Título da seção de estatísticas
- `.estatisticas-grid` - Grid de estatísticas
- `.estatisticas-item` - Item individual
- `.estatisticas-valor` - Valor (grande, negrito)
- `.estatisticas-label` - Label (pequeno, uppercase)

### Utilitários
- `.text-center` - Texto centralizado
- `.text-left` - Texto à esquerda
- `.text-right` - Texto à direita
- `.fw-bold` - Negrito
- `.text-uppercase` - Maiúsculas
- `.mb-small` - Margem inferior pequena (5px)
- `.mb-medium` - Margem inferior média (10px)
- `.mb-large` - Margem inferior grande (15px)

## 📝 Exemplo Completo

```blade
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Meu Documento</title>
    @include('exports.pdf.partials.styles')
</head>
<body>
    <div class="container">
        {{-- Cabeçalho --}}
        @include('exports.pdf.partials.header', ['instituicao' => $instituicao])

        {{-- Título --}}
        <div class="titulo-documento">
            <h1>Título do Documento</h1>
            <h2>Subtítulo (opcional)</h2>
        </div>

        {{-- Informações --}}
        <div class="info-box">
            <div class="info-row">
                <div class="info-cell">
                    <span class="info-label">Campo 1:</span>
                    <span class="info-value">{{ $valor1 }}</span>
                </div>
                <div class="info-cell">
                    <span class="info-label">Campo 2:</span>
                    <span class="info-value">{{ $valor2 }}</span>
                </div>
            </div>
        </div>

        {{-- Tabela --}}
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Coluna 1</th>
                        <th>Coluna 2</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($dados as $item)
                        <tr>
                            <td>{{ $item->campo1 }}</td>
                            <td>{{ $item->campo2 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Rodapé --}}
        @include('exports.pdf.partials.footer', [
            'instituicao' => $instituicao,
            'gerado_em' => $gerado_em,
            'assinaturas' => ['Assinatura 1', 'Assinatura 2']
        ])
    </div>
</body>
</html>
```

## 🎯 Padrão de Margens

- **Margem da Página (@page):** 15mm (topo/baixo) × 20mm (laterais)
- **Padding do Body:** 5mm
- **Margens Internas:** 5mm (elementos dentro do container)

## 🔧 Personalização

### Cores Principais
- **Preto:** `#000`, `#1a1a1a`, `#333`
- **Cinza:** `#666`, `#888`, `#f5f5f5`, `#fafafa`
- **Bordas:** `#333`, `#999`, `#ccc`
- **Positivo:** `#006600`
- **Negativo:** `#d00000`
- **Alerta:** `#fff3cd` (fundo amarelo claro)

### Fontes
- **Família:** 'DejaVu Sans', Arial, sans-serif
- **Tamanho Base:** 10px
- **Títulos:** 15-17px
- **Labels:** 9-9.5px
- **Rodapé:** 7.5px

## ✅ PDFs Atualizados

- [x] mini-pauta.blade.php
- [x] lista-alunos.blade.php
- [ ] pauta-final-disciplina.blade.php
- [ ] pauta-final-turma.blade.php
- [ ] pauta-final-completa.blade.php
- [ ] boletim.blade.php
- [ ] boletim-trimestre.blade.php

## 📦 Próximos Passos

1. Atualizar PDFs restantes usando os partials
2. Aplicar formatação nos Excels (PhpSpreadsheet)
3. Adicionar opção de tema claro/escuro (futuro)
