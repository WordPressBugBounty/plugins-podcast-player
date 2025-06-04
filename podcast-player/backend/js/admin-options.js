import DomManipulation from './partials/options/dom';
import FeedEditor from './partials/options/feededit';
import FeedMigrate from './partials/options/feedmigrate';
import ShortcodeGenerator from './partials/options/shortgen';
import ColorPicker from './partials/options/colorpicker';
import FetchMethod from './partials/options/fetchMethod';
import ChangeDetect from './partials/options/changeDetect';

new DomManipulation();
new FeedEditor();
new FeedMigrate();
new ShortcodeGenerator();

jQuery(function() {
	new ColorPicker();
	new FetchMethod();
	new ChangeDetect();
});