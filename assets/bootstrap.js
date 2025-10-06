import { startStimulusApp } from '@symfony/stimulus-bundle';
import AvailabilityController from './controllers/availability_controller.js';

const app = startStimulusApp();
// register any custom, 3rd party controllers here
app.register('availability', AvailabilityController);
