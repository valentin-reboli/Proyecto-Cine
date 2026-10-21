import { Directive, ElementRef, OnDestroy, OnInit, inject, input } from '@angular/core';

// Hace aparecer el elemento (fade + subida) cuando entra en pantalla al scrollear.
// appRevealDelay: posicion en una grilla, para escalonar las tarjetas de una misma fila.
@Directive({
  selector: '[appReveal]',
  host: { class: 'reveal' },
})
export class RevealDirective implements OnInit, OnDestroy {
  private readonly el = inject(ElementRef<HTMLElement>);
  readonly appRevealDelay = input(0);
  private observer: IntersectionObserver | null = null;

  ngOnInit(): void {
    const node: HTMLElement = this.el.nativeElement;
    node.style.setProperty('--reveal-delay', `${(this.appRevealDelay() % 4) * 90}ms`);

    if (typeof IntersectionObserver === 'undefined') {
      node.classList.add('is-visible');
      return;
    }
    this.observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            node.classList.add('is-visible');
            this.observer?.disconnect();
          }
        }
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
    );
    this.observer.observe(node);
  }

  ngOnDestroy(): void {
    this.observer?.disconnect();
  }
}
