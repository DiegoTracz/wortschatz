// Todos os seletores do DOM do Amazon Notebook num lugar só: o layout não é
// documentado e muda de tempos em tempos — quando o scraper quebrar, é aqui
// que se conserta.
export const selectors = {
    signInForm: 'form[name="signIn"], #ap_email',
    library: '#kp-notebook-library',
    book: '#kp-notebook-library .kp-notebook-library-each-book',
    bookTitle: 'h2.kp-notebook-searchable',
    bookAuthor: 'p.kp-notebook-searchable',
    // Input oculto com o ASIN do livro cujas anotações estão na tela — usado
    // para saber quando a troca de livro terminou de carregar.
    annotationsAsin: 'input#kp-notebook-annotations-asin',
    annotationRow: '#kp-notebook-annotations .a-row.a-spacing-base',
    highlightText: 'span#highlight',
    noteText: 'span#note',
    // Input oculto com a posição numérica crua (evita interpretar "1.234").
    annotationLocation: 'input#kp-annotation-location',
    // Token de paginação; vazio quando não há mais páginas de anotações.
    nextPageToken: '.kp-notebook-annotations-next-page-start',
};
