import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Icon } from '../../shared/icon/icon';

@Component({
  selector: 'cm-header',
  imports: [Icon, RouterLink],
  templateUrl: './header.html',
  styleUrl: './header.scss',
})
export class Header {}
