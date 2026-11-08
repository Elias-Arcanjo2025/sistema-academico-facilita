# Instalação do Tesseract OCR (Assistente Pedagógico)

O módulo de correção assistida usa **Tesseract** como motor OCR gratuito, com fallback para visão OpenRouter quando a confiança é baixa.

## Windows (Laragon)

1. Descarregar o instalador em [UB-Mannheim Tesseract](https://github.com/UB-Mannheim/tesseract/wiki).
2. Instalar em `C:\Program Files\Tesseract-OCR` (ou outro caminho).
3. Durante a instalação, seleccionar o pacote linguístico **Portuguese (`por`)**.
4. Adicionar ao PATH do sistema ou configurar no `.env`:

```env
TESSERACT_PATH=C:\Program Files\Tesseract-OCR\tesseract.exe
TESSERACT_LANG=por
```

5. Reiniciar o Laragon e verificar:

```bash
tesseract --version
tesseract --list-langs
```

## Linux (produção)

```bash
sudo apt update
sudo apt install -y tesseract-ocr tesseract-ocr-por
which tesseract
```

## Variáveis de ambiente

| Variável | Descrição |
|----------|-----------|
| `TESSERACT_PATH` | Caminho do executável (opcional se estiver no PATH) |
| `TESSERACT_LANG` | Idioma OCR (padrão: `por`) |
| `PEDAGOGICO_OCR_CONFIANCA_MIN` | Limiar 0–1 para fallback Vision (padrão: `0.75`) |
| `OPENROUTER_API_KEY` | Chave para fallback Vision e gerações IA |

## Pacote PHP

```bash
composer require thiagoalessio/tesseract_ocr
```

## Notas

- Sem Tesseract instalado, o sistema tenta fallback Vision (custo OpenRouter).
- Ficheiros de correção ficam em `storage/app/pedagogico/correcoes/` (disco privado).
- A nota sugerida **nunca** é gravada automaticamente em `notas` — confirmação manual obrigatória.
