/**
 * Copyright (c) Big Cart Energy. All rights reserved.
 * SPDX-License-Identifier: OSL-3.0
 */
define([
    'jquery',
    'ko',
    'underscore',
    'uiRegistry',
    'Magento_Ui/js/form/element/abstract',
    'BigCartEnergy_CatalogCompleteness/js/form/validation',
    'mage/translate'
], function ($, ko, _, registry, Abstract, validator, $t) {
    'use strict';

    var CONFIG = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.bce_config',
        SETS = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.attribute_set_ids',
        KIND = 'bce_completeness_ruleset_form.bce_completeness_ruleset_form.general.kind';

    /**
     * The key of a keyboard event, from jQuery's event or the browser's own.
     *
     * @param {Object} event
     * @returns {String}
     */
    function keyOf(event) {
        var source = event.originalEvent || event;

        return String(event.key || source.key || '');
    }

    /**
     * The searchable attribute select of a rule row: label and code per option, a note when the attribute
     * is missing from some of the chosen attribute sets, and the duplicate check against the other rows.
     * While the menu is open the focus stays in its search box: a press inside the picker leaves the focus where
     * it is, the arrow keys move a highlight, and the menu closes once the focus has settled outside the picker.
     */
    return Abstract.extend({
        defaults: {
            elementTmpl: 'BigCartEnergy_CatalogCompleteness/form/element/attribute-picker',
            attributes: [],
            open: false,
            filterText: '',
            active: -1,
            chosenSets: [],
            kind: 'attribute_set',
            allSetCount: 0,
            setNames: {},
            listens: {
                value: 'onValueChanged'
            }
        },

        /**
         * @inheritdoc
         */
        initialize: function () {
            var self = this;

            this._super();
            this.validation = _.extend({}, this.validation, {
                'bce-attribute-required': true,
                'bce-attribute-unique': {
                    isDuplicate: function (value) {
                        return self.isDuplicate(value);
                    },
                    message: function () {
                        return self.duplicateMessage();
                    }
                }
            });

            registry.get(CONFIG, function (config) {
                self.attributes(config.bceAttributes || []);
                self.allSetCount = (config.bceAttributeSetNames || []).length;
                _.each(config.bceAttributeSetNames || [], function (set) {
                    self.setNames[String(set.id)] = set.name;
                });
                // A saved row's value is set before this list arrives, so its change found no attribute: publish now.
                self.onValueChanged();
            });
            registry.get(SETS, function (sets) {
                self.chosenSets(_.map(sets.value() || [], String));
                sets.value.subscribe(function (value) {
                    self.chosenSets(_.map(value || [], String));
                });
            });
            registry.get(KIND, function (kind) {
                self.kind(kind.value());
                kind.value.subscribe(function (value) {
                    self.kind(value);
                });
            });

            return this;
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super().observe(['attributes', 'open', 'filterText', 'active', 'chosenSets', 'kind']);

            this.selected = ko.computed(function () {
                var code = this.value();

                return _.find(this.attributes(), function (attribute) {
                    return attribute.code === code;
                }) || null;
            }, this);

            this.filtered = ko.computed(function () {
                var needle = String(this.filterText() || '').toLowerCase().trim();

                return _.filter(this.attributes(), function (attribute) {
                    return !needle || attribute.label.toLowerCase().indexOf(needle) !== -1 || attribute.code.toLowerCase().indexOf(needle) !== -1;
                });
            }, this);

            this.missingSets = ko.computed(function () {
                var selected = this.selected(),
                    chosen = this.kind() === 'channel' ? _.keys(this.setNames) : this.chosenSets();

                if (!selected || !chosen.length) {
                    return [];
                }

                return _.filter(chosen, function (id) {
                    return _.indexOf(_.map(selected.sets || [], String), String(id)) === -1;
                });
            }, this);

            // Typing in the search box highlights the first match, so Enter takes it.
            this.filtered.subscribe(function (list) {
                if (this.open()) {
                    this.active(list.length ? 0 : -1);
                }
            }, this);

            return this;
        },

        /**
         * @returns {String}
         */
        displayLabel: function () {
            var selected = this.selected();

            return selected ? selected.label : this.value() ? this.value() : $t('Choose an attribute');
        },

        /**
         * @returns {String}
         */
        displayCode: function () {
            var selected = this.selected();

            return selected ? selected.code : this.value() || '';
        },

        /**
         * The membership note of an option: "In all N sets" or "In M of N sets".
         *
         * @param {Object} attribute
         * @returns {String}
         */
        setsNote: function (attribute) {
            var relevant = this.kind() === 'channel' ? _.keys(this.setNames) : this.chosenSets(),
                inSets;

            if (!relevant.length) {
                return '';
            }
            inSets = _.filter(relevant, function (id) {
                return _.indexOf(_.map(attribute.sets || [], String), String(id)) !== -1;
            }).length;

            if (inSets === relevant.length) {
                return relevant.length === 1 ? $t('In the chosen set') : $t('In all %1 sets').replace('%1', relevant.length);
            }

            return $t('In %1 of %2 sets').replace('%1', inSets).replace('%2', relevant.length);
        },

        /**
         * @param {Object} attribute
         * @returns {Boolean}
         */
        isPartial: function (attribute) {
            var relevant = this.kind() === 'channel' ? _.keys(this.setNames) : this.chosenSets();

            return relevant.length > 0 && _.some(relevant, function (id) {
                return _.indexOf(_.map(attribute.sets || [], String), String(id)) === -1;
            });
        },

        /**
         * @returns {String}
         */
        warningBadge: function () {
            var missing = this.missingSets();

            if (!missing.length) {
                return '';
            }

            return missing.length === 1 ? $t('Not in 1 set') : $t('Not in %1 sets').replace('%1', missing.length);
        },

        /**
         * @returns {String}
         */
        warningLine: function () {
            var self = this,
                missing = this.missingSets(),
                names;

            if (!missing.length) {
                return '';
            }
            names = _.map(missing, function (id) {
                return self.setNames[String(id)] || id;
            });

            return $t('Not in %1. Products in those sets fail this rule because they cannot hold a value.')
                .replace('%1', names.join(', '));
        },

        toggle: function () {
            if (this.open()) {
                this.closeMenu(true);
            } else {
                this.openMenu();
            }
        },

        close: function () {
            this.closeMenu(true);
        },

        /**
         * Open the menu with an empty search, the current attribute highlighted and the focus in the search box.
         */
        openMenu: function () {
            if (this.disabled()) {
                return;
            }

            if (!this.open()) {
                this.filterText('');
                this.open(true);
            }
            this.active(_.indexOf(_.pluck(this.filtered(), 'code'), this.value()));
            this.focusOwn('-search');
        },

        /**
         * Close the menu. With keepFocus the toggle takes the focus before the search box goes, so the focus never
         * falls to the page and the move does not count as a visit from outside (see onFocusIn).
         *
         * @param {Boolean} keepFocus
         */
        closeMenu: function (keepFocus) {
            if (keepFocus) {
                this.focusOwn('-toggle');
            }
            this.open(false);
            this.active(-1);
        },

        /**
         * Focus one of the picker's own elements (its uid plus a suffix), now or, while the menu is still being
         * rendered, right after.
         *
         * @param {String} suffix
         */
        focusOwn: function (suffix) {
            var id = this.uid + suffix,
                element = document.getElementById(id);

            if (element) {
                element.focus();

                return;
            }
            _.defer(function () {
                var later = document.getElementById(id);

                if (later) {
                    later.focus();
                }
            });
        },

        /**
         * @param {Object} attribute
         */
        choose: function (attribute) {
            this.value(attribute.code);
            this.closeMenu(true);
            this.validate();
        },

        /**
         * Choose the highlighted attribute, if one is highlighted.
         */
        chooseActive: function () {
            var attribute = this.filtered()[this.active()];

            if (attribute) {
                this.choose(attribute);
            }
        },

        /**
         * Move the highlight one option down (1) or up (-1), staying within the list, and scroll it into view.
         *
         * @param {Number} step
         */
        move: function (step) {
            var count = this.filtered().length,
                index = this.active(),
                element;

            if (!count) {
                return;
            }

            if (index < 0) {
                index = step > 0 ? 0 : count - 1;
            } else {
                index = Math.max(0, Math.min(count - 1, index + step));
            }
            this.active(index);
            element = document.getElementById(this.optionId(index));

            if (element && element.scrollIntoView) {
                element.scrollIntoView({block: 'nearest'});
            }
        },

        /**
         * The id of an option of the menu.
         *
         * @param {Number} index
         * @returns {String}
         */
        optionId: function (index) {
            return this.uid + '-option-' + index;
        },

        /**
         * The highlighted option's id, for aria-activedescendant; null when nothing is highlighted.
         *
         * @returns {String|null}
         */
        activeId: function () {
            var index = this.active();

            return index >= 0 && index < this.filtered().length ? this.optionId(index) : null;
        },

        /**
         * @param {HTMLElement} element
         * @returns {Boolean}
         */
        isToggle: function (element) {
            return element ? element.id === this.uid + '-toggle' : false;
        },

        /**
         * A press inside the picker leaves the focus where it is (in the search box while the menu is open), so a
         * click on an option or on the toggle lands before any focus change. The search box takes its own presses.
         *
         * @param {Object} data
         * @param {Event} event
         * @returns {Boolean}
         */
        onPress: function (data, event) {
            return event.target ? event.target.id === this.uid + '-search' : false;
        },

        /**
         * Focus coming to the toggle from an element outside the picker opens the menu (a Tab into the row, or the
         * rules summary focusing the row); moves between the picker's own elements do not.
         *
         * @param {Object} data
         * @param {Event} event
         * @returns {Boolean}
         */
        onFocusIn: function (data, event) {
            var from = event.relatedTarget;

            if (!this.open() && this.isToggle(event.target) && from && !event.currentTarget.contains(from)) {
                this.openMenu();
            }

            return true;
        },

        /**
         * Focus leaving one of the picker's elements: once it has settled, the menu closes if the focus is outside
         * the picker. A move between the toggle and the search box keeps it open.
         *
         * @param {Object} data
         * @param {Event} event
         * @returns {Boolean}
         */
        onFocusOut: function (data, event) {
            var self = this,
                root = event.currentTarget;

            if (this.open()) {
                _.defer(function () {
                    if (self.open() && !root.contains(document.activeElement)) {
                        self.closeMenu(false);
                    }
                });
            }

            return true;
        },

        /**
         * Keys of the picker. Closed: Arrow Down or Up, or a character typed on the toggle, opens the menu. Open:
         * Arrow Down and Up move the highlight, Enter chooses the highlighted attribute and Escape closes without a
         * change. Tab is left to the browser; the menu closes once the focus is outside the picker.
         *
         * @param {Object} data
         * @param {Event} event
         * @returns {Boolean}
         */
        onKey: function (data, event) {
            var key = keyOf(event),
                onToggle = this.isToggle(event.target),
                arrow = ['ArrowDown', 'Down', 'ArrowUp', 'Up'].indexOf(key) !== -1,
                typed = key.length === 1 && key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey;

            if (!this.open()) {
                if (onToggle && (arrow || typed)) {
                    this.openMenu();
                    this.filterText(typed ? key : '');

                    return false;
                }

                return true;
            }

            if (arrow) {
                this.move(key === 'ArrowDown' || key === 'Down' ? 1 : -1);
                this.focusOwn('-search');

                return false;
            }

            if (key === 'Escape' || key === 'Esc') {
                this.closeMenu(true);

                return false;
            }

            if (key === 'Enter') {
                // On the toggle, Enter is the button's own click, which closes the menu.
                if (!onToggle) {
                    this.chooseActive();
                }

                return onToggle;
            }

            if (onToggle && typed) {
                this.focusOwn('-search');
                this.filterText(this.filterText() + key);

                return false;
            }

            return true;
        },

        /**
         * @param {String} value
         * @returns {Boolean}
         */
        isDuplicate: function (value) {
            var record = this.containers && this.containers[0] ? this.containers[0] : null,
                parent = record && record.parentComponent ? record.parentComponent() : null;

            return !!(parent && parent.isDuplicateAttribute && parent.isDuplicateAttribute(value, record));
        },

        /**
         * @returns {String}
         */
        duplicateMessage: function () {
            var record = this.containers && this.containers[0] ? this.containers[0] : null,
                parent = record && record.parentComponent ? record.parentComponent() : null,
                first = parent && parent.firstRowWithAttribute ? parent.firstRowWithAttribute(this.value(), record) : null,
                label = this.displayLabel();

            if (first !== null) {
                return $t('%1 already has a rule (rule %2). Use one rule per attribute.').replace('%1', label).replace('%2', first + 1);
            }

            return $t('%1 already has a rule. Use one rule per attribute.').replace('%1', label);
        },

        /**
         * Tell the siblings (rule type, parameter) which input type the attribute has: on a change of the value,
         * and once the attribute list has loaded. set() notifies them only when the input type or label differs.
         */
        onValueChanged: function () {
            var selected = this.selected();

            this.set('attributeInput', selected ? selected.input : '');
            this.set('attributeLabel', selected ? selected.label : '');
        },

        /**
         * @inheritdoc
         */
        validate: function () {
            var result = this._super();

            $(document).trigger('bce-completeness:rules-changed');

            return result;
        }
    });
});
