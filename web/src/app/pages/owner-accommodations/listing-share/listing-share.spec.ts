import { TestBed } from '@angular/core/testing';

import { environment } from '../../../../environments/environment';
import { ListingShare } from './listing-share';

const URL = `${environment.siteUrl}/logement/mobil-home-les-pins`;

describe('ListingShare', () => {
  function create() {
    const fixture = TestBed.createComponent(ListingShare);
    fixture.componentRef.setInput('slug', 'mobil-home-les-pins');
    fixture.componentRef.setInput('label', 'Mobil-home · Les Pins');
    fixture.detectChanges();

    return fixture;
  }

  it('shares the public French address of the listing', () => {
    const fixture = create();

    expect(fixture.componentInstance.url()).toBe(URL);
    expect(fixture.nativeElement.querySelector('input').value).toBe(URL);
  });

  it('copies the link and says so in words', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
    const fixture = create();

    await fixture.componentInstance.copy();
    fixture.detectChanges();

    expect(writeText).toHaveBeenCalledWith(URL);
    expect(fixture.nativeElement.textContent).toContain('Lien copié');
  });

  it('asks to copy by hand when the clipboard is refused', async () => {
    const writeText = vi.fn().mockRejectedValue(new Error('denied'));
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
    const fixture = create();

    await fixture.componentInstance.copy();
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('[role=alert]').textContent).toContain('copiez-le');
  });
});
