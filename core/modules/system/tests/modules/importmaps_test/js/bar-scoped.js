class Bar {
  constructor(el) {
    this.el = el;
  }

  init() {
    this.el.textContent = 'Scoped bar';
  }
}

export default Bar;
