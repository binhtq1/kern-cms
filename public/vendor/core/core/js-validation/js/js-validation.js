(function() {
  "use strict";
  (function(factory) {
    if (typeof define === "function" && define.amd) {
      define(["jquery"], factory);
    } else {
      factory(jQuery);
    }
  })(function($2) {
    $2.extend($2.fn, {
      // http://jqueryvalidation.org/validate/
      validate: function(options) {
        if (!this.length) {
          if (options && options.debug && window.console) {
            console.warn("Nothing selected, can't validate, returning nothing.");
          }
          return;
        }
        var validator = $2.data(this[0], "validator");
        if (validator) {
          return validator;
        }
        this.attr("novalidate", "novalidate");
        validator = new $2.validator(options, this[0]);
        $2.data(this[0], "validator", validator);
        if (validator.settings.onsubmit) {
          this.on("click.validate", ":submit", function(event) {
            validator.submitButton = event.target;
            if ($2(this).hasClass("cancel")) {
              validator.cancelSubmit = true;
            }
            if ($2(this).attr("formnovalidate") !== void 0) {
              validator.cancelSubmit = true;
            }
          });
          this.on("submit.validate", function(event) {
            if (validator.settings.debug) {
              event.preventDefault();
            }
            function handle() {
              var hidden, result;
              if (validator.submitButton) {
                hidden = $2("<input type='hidden'/>").attr("name", validator.submitButton.name).val($2(validator.submitButton).val()).appendTo(validator.currentForm);
              }
              if (validator.settings.submitHandler) {
                result = validator.settings.submitHandler.call(validator, validator.currentForm, event);
                if (validator.submitButton) {
                  hidden.remove();
                }
                if (result !== void 0) {
                  return result;
                }
                return false;
              }
              return true;
            }
            if (validator.cancelSubmit) {
              validator.cancelSubmit = false;
              return handle();
            }
            if (validator.form()) {
              if (validator.pendingRequest) {
                validator.formSubmitted = true;
                return false;
              }
              return handle();
            } else {
              validator.focusInvalid();
              return false;
            }
          });
        }
        return validator;
      },
      // http://jqueryvalidation.org/valid/
      valid: function() {
        var valid, validator, errorList;
        if ($2(this[0]).is("form")) {
          valid = this.validate().form();
        } else {
          errorList = [];
          valid = true;
          validator = $2(this[0].form).validate();
          this.each(function() {
            valid = validator.element(this) && valid;
            errorList = errorList.concat(validator.errorList);
          });
          validator.errorList = errorList;
        }
        return valid;
      },
      // http://jqueryvalidation.org/rules/
      rules: function(command, argument) {
        var element2 = this[0], settings, staticRules, existingRules, data, param, filtered;
        if (command) {
          settings = $2.data(element2.form, "validator").settings;
          staticRules = settings.rules;
          existingRules = $2.validator.staticRules(element2);
          switch (command) {
            case "add":
              $2.extend(existingRules, $2.validator.normalizeRule(argument));
              delete existingRules.messages;
              staticRules[element2.name] = existingRules;
              if (argument.messages) {
                settings.messages[element2.name] = $2.extend(
                  settings.messages[element2.name],
                  argument.messages
                );
              }
              break;
            case "remove":
              if (!argument) {
                delete staticRules[element2.name];
                return existingRules;
              }
              filtered = {};
              $2.each(argument.split(/\s/), function(index, method) {
                filtered[method] = existingRules[method];
                delete existingRules[method];
                if (method === "required") {
                  $2(element2).removeAttr("aria-required");
                }
              });
              return filtered;
          }
        }
        data = $2.validator.normalizeRules(
          $2.extend(
            {},
            $2.validator.classRules(element2),
            $2.validator.attributeRules(element2),
            $2.validator.dataRules(element2),
            $2.validator.staticRules(element2)
          ),
          element2
        );
        if (data.required) {
          param = data.required;
          delete data.required;
          data = $2.extend({ required: param }, data);
          $2(element2).attr("aria-required", "true");
        }
        if (data.remote) {
          param = data.remote;
          delete data.remote;
          data = $2.extend(data, { remote: param });
        }
        return data;
      }
    });
    $2.extend($2.expr[":"], {
      // http://jqueryvalidation.org/blank-selector/
      blank: function(a) {
        return !String($2(a).val() ?? "").trim();
      },
      // http://jqueryvalidation.org/filled-selector/
      filled: function(a) {
        return !!String($2(a).val() ?? "").trim();
      },
      // http://jqueryvalidation.org/unchecked-selector/
      unchecked: function(a) {
        return !$2(a).prop("checked");
      }
    });
    $2.validator = function(options, form) {
      this.settings = $2.extend(true, {}, $2.validator.defaults, options);
      this.currentForm = form;
      this.init();
    };
    $2.validator.format = function(source, params2) {
      if (arguments.length === 1) {
        return function() {
          var args = $2.makeArray(arguments);
          args.unshift(source);
          return $2.validator.format.apply(this, args);
        };
      }
      if (arguments.length > 2 && params2.constructor !== Array) {
        params2 = $2.makeArray(arguments).slice(1);
      }
      if (params2.constructor !== Array) {
        params2 = [params2];
      }
      $2.each(params2, function(i, n) {
        source = source.replace(new RegExp("\\{" + i + "\\}", "g"), function() {
          return n;
        });
      });
      return source;
    };
    $2.extend($2.validator, {
      defaults: {
        messages: {},
        groups: {},
        rules: {},
        errorClass: "error",
        validClass: "valid",
        errorElement: "label",
        focusCleanup: false,
        focusInvalid: true,
        errorContainer: $2([]),
        errorLabelContainer: $2([]),
        onsubmit: true,
        ignore: ":hidden",
        ignoreTitle: false,
        onfocusin: function(element2) {
          this.lastActive = element2;
          if (this.settings.focusCleanup) {
            if (this.settings.unhighlight) {
              this.settings.unhighlight.call(
                this,
                element2,
                this.settings.errorClass,
                this.settings.validClass
              );
            }
            this.hideThese(this.errorsFor(element2));
          }
        },
        onfocusout: function(element2) {
          if (!this.checkable(element2) && (element2.name in this.submitted || !this.optional(element2))) {
            this.element(element2);
          }
        },
        onkeyup: function(element2, event) {
          var excludedKeys = [16, 17, 18, 20, 35, 36, 37, 38, 39, 40, 45, 144, 225];
          if (event.which === 9 && this.elementValue(element2) === "" || $2.inArray(event.keyCode, excludedKeys) !== -1) {
            return;
          } else if (element2.name in this.submitted || element2 === this.lastElement) {
            this.element(element2);
          }
        },
        onclick: function(element2) {
          if (element2.name in this.submitted) {
            this.element(element2);
          } else if (element2.parentNode.name in this.submitted) {
            this.element(element2.parentNode);
          }
        },
        highlight: function(element2, errorClass, validClass) {
          if (element2.type === "radio") {
            this.findByName(element2.name).addClass(errorClass).removeClass(validClass);
          } else {
            $2(element2).addClass(errorClass).removeClass(validClass);
          }
        },
        unhighlight: function(element2, errorClass, validClass) {
          if (element2.type === "radio") {
            this.findByName(element2.name).removeClass(errorClass).addClass(validClass);
          } else {
            $2(element2).removeClass(errorClass).addClass(validClass);
          }
        }
      },
      // http://jqueryvalidation.org/jQuery.validator.setDefaults/
      setDefaults: function(settings) {
        $2.extend($2.validator.defaults, settings);
      },
      messages: {
        required: "This field is required.",
        remote: "Please fix this field.",
        email: "Please enter a valid email address.",
        url: "Please enter a valid URL.",
        date: "Please enter a valid date.",
        dateISO: "Please enter a valid date ( ISO ).",
        number: "Please enter a valid number.",
        digits: "Please enter only digits.",
        creditcard: "Please enter a valid credit card number.",
        equalTo: "Please enter the same value again.",
        maxlength: $2.validator.format("Please enter no more than {0} characters."),
        minlength: $2.validator.format("Please enter at least {0} characters."),
        rangelength: $2.validator.format("Please enter a value between {0} and {1} characters long."),
        range: $2.validator.format("Please enter a value between {0} and {1}."),
        max: $2.validator.format("Please enter a value less than or equal to {0}."),
        min: $2.validator.format("Please enter a value greater than or equal to {0}.")
      },
      autoCreateRanges: false,
      prototype: {
        init: function() {
          this.labelContainer = $2(this.settings.errorLabelContainer);
          this.errorContext = this.labelContainer.length && this.labelContainer || $2(this.currentForm);
          this.containers = $2(this.settings.errorContainer).add(this.settings.errorLabelContainer);
          this.submitted = {};
          this.valueCache = {};
          this.pendingRequest = 0;
          this.pending = {};
          this.invalid = {};
          this.reset();
          var groups = this.groups = {}, rules;
          $2.each(this.settings.groups, function(key, value2) {
            if (typeof value2 === "string") {
              value2 = value2.split(/\s/);
            }
            $2.each(value2, function(index, name) {
              groups[name] = key;
            });
          });
          rules = this.settings.rules;
          $2.each(rules, function(key, value2) {
            rules[key] = $2.validator.normalizeRule(value2);
          });
          function delegate(event) {
            var validator = $2.data(this.form, "validator"), eventType = "on" + event.type.replace(/^validate/, ""), settings = validator ? validator.settings : {};
            if (settings[eventType] && !$2(this).is(settings.ignore)) {
              settings[eventType].call(validator, this, event);
            }
          }
          $2(this.currentForm).on(
            "focusin.validate focusout.validate keyup.validate",
            ":text, [type='password'], [type='file'], select, textarea, [type='number'], [type='search'], [type='tel'], [type='url'], [type='email'], [type='datetime'], [type='date'], [type='month'], [type='week'], [type='time'], [type='datetime-local'], [type='range'], [type='color'], [type='radio'], [type='checkbox']",
            delegate
          ).on("click.validate", "select, option, [type='radio'], [type='checkbox']", delegate);
          if (this.settings.invalidHandler) {
            $2(this.currentForm).on("invalid-form.validate", this.settings.invalidHandler);
          }
          $2(this.currentForm).find("[required], [data-rule-required], .required:not(label)").attr("aria-required", "true");
        },
        // http://jqueryvalidation.org/Validator.form/
        form: function() {
          this.checkForm();
          $2.extend(this.submitted, this.errorMap);
          this.invalid = $2.extend({}, this.errorMap);
          if (!this.valid()) {
            $2(this.currentForm).triggerHandler("invalid-form", [this]);
          }
          this.showErrors();
          return this.valid();
        },
        checkForm: function() {
          this.prepareForm();
          for (var i = 0, elements = this.currentElements = this.elements(); elements[i]; i++) {
            this.check(elements[i]);
          }
          return this.valid();
        },
        // http://jqueryvalidation.org/Validator.element/
        element: function(element2) {
          var cleanElement = this.clean(element2), checkElement = this.validationTargetFor(cleanElement), result = true;
          this.lastElement = checkElement;
          if (checkElement === void 0) {
            delete this.invalid[cleanElement.name];
          } else {
            this.prepareElement(checkElement);
            this.currentElements = $2(checkElement);
            result = this.check(checkElement) !== false;
            if (result) {
              delete this.invalid[checkElement.name];
            } else {
              this.invalid[checkElement.name] = true;
            }
          }
          $2(element2).attr("aria-invalid", !result);
          if (!this.numberOfInvalids()) {
            this.toHide = this.toHide.add(this.containers);
          }
          this.showErrors();
          return result;
        },
        // http://jqueryvalidation.org/Validator.showErrors/
        showErrors: function(errors) {
          if (errors) {
            $2.extend(this.errorMap, errors);
            this.errorList = [];
            for (var name in errors) {
              this.errorList.push({
                message: errors[name],
                element: this.findByName(name)[0]
              });
            }
            this.successList = $2.grep(this.successList, function(element2) {
              return !(element2.name in errors);
            });
          }
          if (this.settings.showErrors) {
            this.settings.showErrors.call(this, this.errorMap, this.errorList);
          } else {
            this.defaultShowErrors();
          }
        },
        // http://jqueryvalidation.org/Validator.resetForm/
        resetForm: function() {
          if ($2.fn.resetForm) {
            $2(this.currentForm).resetForm();
          }
          this.submitted = {};
          this.lastElement = null;
          this.prepareForm();
          this.hideErrors();
          var i, elements = this.elements().removeData("previousValue").removeAttr("aria-invalid");
          if (this.settings.unhighlight) {
            for (i = 0; elements[i]; i++) {
              this.settings.unhighlight.call(this, elements[i], this.settings.errorClass, "");
            }
          } else {
            elements.removeClass(this.settings.errorClass);
          }
        },
        numberOfInvalids: function() {
          return this.objectLength(this.invalid);
        },
        objectLength: function(obj) {
          var count = 0, i;
          for (i in obj) {
            count++;
          }
          return count;
        },
        hideErrors: function() {
          this.hideThese(this.toHide);
        },
        hideThese: function(errors) {
          errors.not(this.containers).text("");
          this.addWrapper(errors).hide();
        },
        valid: function() {
          return this.size() === 0;
        },
        size: function() {
          return this.errorList.length;
        },
        focusInvalid: function() {
          if (this.settings.focusInvalid) {
            try {
              $2(this.findLastActive() || this.errorList.length && this.errorList[0].element || []).filter(":visible").focus().trigger("focusin");
            } catch (e) {
            }
          }
        },
        findLastActive: function() {
          var lastActive = this.lastActive;
          return lastActive && $2.grep(this.errorList, function(n) {
            return n.element.name === lastActive.name;
          }).length === 1 && lastActive;
        },
        elements: function() {
          var validator = this, rulesCache = {};
          return $2(this.currentForm).find("input, select, textarea").not(":submit, :reset, :image, :disabled").not(this.settings.ignore).filter(function() {
            if (!this.name && validator.settings.debug && window.console) {
              console.error("%o has no name assigned", this);
            }
            if (this.name in rulesCache || !validator.objectLength($2(this).rules())) {
              return false;
            }
            rulesCache[this.name] = true;
            return true;
          });
        },
        clean: function(selector) {
          return $2(selector)[0];
        },
        errors: function() {
          var errorClass = this.settings.errorClass.split(" ").join(".");
          return $2(this.settings.errorElement + "." + errorClass, this.errorContext);
        },
        reset: function() {
          this.successList = [];
          this.errorList = [];
          this.errorMap = {};
          this.toShow = $2([]);
          this.toHide = $2([]);
          this.currentElements = $2([]);
        },
        prepareForm: function() {
          this.reset();
          this.toHide = this.errors().add(this.containers);
        },
        prepareElement: function(element2) {
          this.reset();
          this.toHide = this.errorsFor(element2);
        },
        elementValue: function(element2) {
          var val, $element = $2(element2), type = element2.type;
          if (type === "radio" || type === "checkbox") {
            return this.findByName(element2.name).filter(":checked").val();
          } else if (type === "number" && typeof element2.validity !== "undefined") {
            return element2.validity.badInput ? false : $element.val();
          }
          val = $element.val();
          if (typeof val === "string") {
            return val.replace(/\r/g, "");
          }
          return val;
        },
        check: function(element2) {
          element2 = this.validationTargetFor(this.clean(element2));
          var rules = $2(element2).rules(), rulesCount = $2.map(rules, function(n, i) {
            return i;
          }).length, dependencyMismatch = false, val = this.elementValue(element2), result, method, rule;
          for (method in rules) {
            rule = { method, parameters: rules[method] };
            try {
              result = $2.validator.methods[method].call(this, val, element2, rule.parameters);
              if (result === "dependency-mismatch" && rulesCount === 1) {
                dependencyMismatch = true;
                continue;
              }
              dependencyMismatch = false;
              if (result === "pending") {
                this.toHide = this.toHide.not(this.errorsFor(element2));
                return;
              }
              if (!result) {
                this.formatAndAdd(element2, rule);
                return false;
              }
            } catch (e) {
              if (this.settings.debug && window.console) {
                console.log(
                  "Exception occurred when checking element " + element2.id + ", check the '" + rule.method + "' method.",
                  e
                );
              }
              if (e instanceof TypeError) {
                e.message += ".  Exception occurred when checking element " + element2.id + ", check the '" + rule.method + "' method.";
              }
              throw e;
            }
          }
          if (dependencyMismatch) {
            return;
          }
          if (this.objectLength(rules)) {
            this.successList.push(element2);
          }
          return true;
        },
        // return the custom message for the given element and validation method
        // specified in the element's HTML5 data attribute
        // return the generic message if present and no method specific message is present
        customDataMessage: function(element2, method) {
          return $2(element2).data("msg" + method.charAt(0).toUpperCase() + method.substring(1).toLowerCase()) || $2(element2).data("msg");
        },
        // return the custom message for the given element name and validation method
        customMessage: function(name, method) {
          var m = this.settings.messages[name];
          return m && (m.constructor === String ? m : m[method]);
        },
        // return the first defined argument, allowing empty strings
        findDefined: function() {
          for (var i = 0; i < arguments.length; i++) {
            if (arguments[i] !== void 0) {
              return arguments[i];
            }
          }
          return void 0;
        },
        defaultMessage: function(element2, method) {
          return this.findDefined(
            this.customMessage(element2.name, method),
            this.customDataMessage(element2, method),
            // title is never undefined, so handle empty string as undefined
            !this.settings.ignoreTitle && element2.title || void 0,
            $2.validator.messages[method],
            "<strong>Warning: No message defined for " + element2.name + "</strong>"
          );
        },
        formatAndAdd: function(element2, rule) {
          var message = this.defaultMessage(element2, rule.method), theregex = /\$?\{(\d+)\}/g;
          if (typeof message === "function") {
            message = message.call(this, rule.parameters, element2);
          } else if (theregex.test(message)) {
            message = $2.validator.format(message.replace(theregex, "{$1}"), rule.parameters);
          }
          this.errorList.push({
            message,
            element: element2,
            method: rule.method
          });
          this.errorMap[element2.name] = message;
          this.submitted[element2.name] = message;
        },
        addWrapper: function(toToggle) {
          if (this.settings.wrapper) {
            toToggle = toToggle.add(toToggle.parent(this.settings.wrapper));
          }
          return toToggle;
        },
        defaultShowErrors: function() {
          var i, elements, error;
          for (i = 0; this.errorList[i]; i++) {
            error = this.errorList[i];
            if (this.settings.highlight) {
              this.settings.highlight.call(
                this,
                error.element,
                this.settings.errorClass,
                this.settings.validClass
              );
            }
            this.showLabel(error.element, error.message);
          }
          if (this.errorList.length) {
            this.toShow = this.toShow.add(this.containers);
          }
          if (this.settings.success) {
            for (i = 0; this.successList[i]; i++) {
              this.showLabel(this.successList[i]);
            }
          }
          if (this.settings.unhighlight) {
            for (i = 0, elements = this.validElements(); elements[i]; i++) {
              this.settings.unhighlight.call(
                this,
                elements[i],
                this.settings.errorClass,
                this.settings.validClass
              );
            }
          }
          this.toHide = this.toHide.not(this.toShow);
          this.hideErrors();
          this.addWrapper(this.toShow).show();
        },
        validElements: function() {
          return this.currentElements.not(this.invalidElements());
        },
        invalidElements: function() {
          return $2(this.errorList).map(function() {
            return this.element;
          });
        },
        showLabel: function(element2, message) {
          var place, group, errorID, error = this.errorsFor(element2), elementID = this.idOrName(element2), describedBy = $2(element2).attr("aria-describedby");
          if (error.length) {
            error.removeClass(this.settings.validClass).addClass(this.settings.errorClass);
            error.html(message);
          } else {
            error = $2("<" + this.settings.errorElement + ">").attr("id", elementID + "-error").addClass(this.settings.errorClass).html(message || "");
            place = error;
            if (this.settings.wrapper) {
              place = error.hide().show().wrap("<" + this.settings.wrapper + "/>").parent();
            }
            if (this.labelContainer.length) {
              this.labelContainer.append(place);
            } else if (this.settings.errorPlacement) {
              this.settings.errorPlacement(place, $2(element2));
            } else {
              place.insertAfter(element2);
            }
            if (error.is("label")) {
              error.attr("for", elementID);
            } else if (error.parents("label[for='" + elementID + "']").length === 0) {
              errorID = error.attr("id").replace(/(:|\.|\[|\]|\$)/g, "\\$1");
              if (!describedBy) {
                describedBy = errorID;
              } else if (!describedBy.match(new RegExp("\\b" + errorID + "\\b"))) {
                describedBy += " " + errorID;
              }
              $2(element2).attr("aria-describedby", describedBy);
              group = this.groups[element2.name];
              if (group) {
                $2.each(this.groups, function(name, testgroup) {
                  if (testgroup === group) {
                    $2("[name='" + name + "']", this.currentForm).attr(
                      "aria-describedby",
                      error.attr("id")
                    );
                  }
                });
              }
            }
          }
          if (!message && this.settings.success) {
            error.text("");
            if (typeof this.settings.success === "string") {
              error.addClass(this.settings.success);
            } else {
              this.settings.success(error, element2);
            }
          }
          this.toShow = this.toShow.add(error);
        },
        errorsFor: function(element2) {
          var name = this.idOrName(element2), describer = $2(element2).attr("aria-describedby"), selector = "label[for='" + name + "'], label[for='" + name + "'] *";
          if (describer) {
            selector = selector + ", #" + describer.replace(/\s+/g, ", #");
          }
          return this.errors().filter(selector);
        },
        idOrName: function(element2) {
          return this.groups[element2.name] || (this.checkable(element2) ? element2.name : element2.id || element2.name);
        },
        validationTargetFor: function(element2) {
          if (this.checkable(element2)) {
            element2 = this.findByName(element2.name);
          }
          return $2(element2).not(this.settings.ignore)[0];
        },
        checkable: function(element2) {
          return /radio|checkbox/i.test(element2.type);
        },
        findByName: function(name) {
          return $2(this.currentForm).find("[name='" + name + "']");
        },
        getLength: function(value2, element2) {
          switch (element2.nodeName.toLowerCase()) {
            case "select":
              return $2("option:selected", element2).length;
            case "input":
              if (this.checkable(element2)) {
                return this.findByName(element2.name).filter(":checked").length;
              }
          }
          return value2.length;
        },
        depend: function(param, element2) {
          return this.dependTypes[typeof param] ? this.dependTypes[typeof param](param, element2) : true;
        },
        dependTypes: {
          boolean: function(param) {
            return param;
          },
          string: function(param, element2) {
            return !!$2(param, element2.form).length;
          },
          function: function(param, element2) {
            return param(element2);
          }
        },
        optional: function(element2) {
          var val = this.elementValue(element2);
          return !$2.validator.methods.required.call(this, val, element2) && "dependency-mismatch";
        },
        startRequest: function(element2) {
          if (!this.pending[element2.name]) {
            this.pendingRequest++;
            this.pending[element2.name] = true;
          }
        },
        stopRequest: function(element2, valid) {
          this.pendingRequest--;
          if (this.pendingRequest < 0) {
            this.pendingRequest = 0;
          }
          delete this.pending[element2.name];
          if (valid && this.pendingRequest === 0 && this.formSubmitted && this.form() && this.pendingRequest === 0) {
            $2(this.currentForm).trigger("submit");
            this.formSubmitted = false;
          } else if (!valid && this.pendingRequest === 0 && this.formSubmitted) {
            $2(this.currentForm).triggerHandler("invalid-form", [this]);
            this.formSubmitted = false;
          }
        },
        previousValue: function(element2) {
          return $2.data(element2, "previousValue") || $2.data(element2, "previousValue", {
            old: null,
            valid: true,
            message: this.defaultMessage(element2, "remote")
          });
        },
        // cleans up all forms and elements, removes validator-specific events
        destroy: function() {
          this.resetForm();
          $2(this.currentForm).off(".validate").removeData("validator");
        }
      },
      classRuleSettings: {
        required: { required: true },
        email: { email: true },
        url: { url: true },
        date: { date: true },
        dateISO: { dateISO: true },
        number: { number: true },
        digits: { digits: true },
        creditcard: { creditcard: true }
      },
      addClassRules: function(className, rules) {
        if (className.constructor === String) {
          this.classRuleSettings[className] = rules;
        } else {
          $2.extend(this.classRuleSettings, className);
        }
      },
      classRules: function(element2) {
        var rules = {}, classes = $2(element2).attr("class");
        if (classes) {
          $2.each(classes.split(" "), function() {
            if (this in $2.validator.classRuleSettings) {
              $2.extend(rules, $2.validator.classRuleSettings[this]);
            }
          });
        }
        return rules;
      },
      normalizeAttributeRule: function(rules, type, method, value2) {
        if (/min|max/.test(method) && (type === null || /number|range|text/.test(type))) {
          value2 = Number(value2);
          if (isNaN(value2)) {
            value2 = void 0;
          }
        }
        if (value2 || value2 === 0) {
          rules[method] = value2;
        } else if (type === method && type !== "range") {
          rules[method] = true;
        }
      },
      attributeRules: function(element2) {
        var rules = {}, $element = $2(element2), type = element2.getAttribute("type"), method, value2;
        for (method in $2.validator.methods) {
          if (method === "required") {
            value2 = element2.getAttribute(method);
            if (value2 === "") {
              value2 = true;
            }
            value2 = !!value2;
          } else {
            value2 = $element.attr(method);
          }
          this.normalizeAttributeRule(rules, type, method, value2);
        }
        if (rules.maxlength && /-1|2147483647|524288/.test(rules.maxlength)) {
          delete rules.maxlength;
        }
        return rules;
      },
      dataRules: function(element2) {
        var rules = {}, $element = $2(element2), type = element2.getAttribute("type"), method, value2;
        for (method in $2.validator.methods) {
          value2 = $element.data("rule" + method.charAt(0).toUpperCase() + method.substring(1).toLowerCase());
          this.normalizeAttributeRule(rules, type, method, value2);
        }
        return rules;
      },
      staticRules: function(element2) {
        var rules = {}, validator = $2.data(element2.form, "validator");
        if (validator.settings.rules) {
          rules = $2.validator.normalizeRule(validator.settings.rules[element2.name]) || {};
        }
        return rules;
      },
      normalizeRules: function(rules, element2) {
        $2.each(rules, function(prop, val) {
          if (val === false) {
            delete rules[prop];
            return;
          }
          if (val.param || val.depends) {
            var keepRule = true;
            switch (typeof val.depends) {
              case "string":
                keepRule = !!$2(val.depends, element2.form).length;
                break;
              case "function":
                keepRule = val.depends.call(element2, element2);
                break;
            }
            if (keepRule) {
              rules[prop] = val.param !== void 0 ? val.param : true;
            } else {
              delete rules[prop];
            }
          }
        });
        $2.each(rules, function(rule, parameter) {
          rules[rule] = typeof parameter === "function" ? parameter(element2) : parameter;
        });
        $2.each(["minlength", "maxlength"], function() {
          if (rules[this]) {
            rules[this] = Number(rules[this]);
          }
        });
        $2.each(["rangelength", "range"], function() {
          var parts;
          if (rules[this]) {
            if (Array.isArray(rules[this])) {
              rules[this] = [Number(rules[this][0]), Number(rules[this][1])];
            } else if (typeof rules[this] === "string") {
              parts = rules[this].replace(/[\[\]]/g, "").split(/[\s,]+/);
              rules[this] = [Number(parts[0]), Number(parts[1])];
            }
          }
        });
        if ($2.validator.autoCreateRanges) {
          if (rules.min != null && rules.max != null) {
            rules.range = [rules.min, rules.max];
            delete rules.min;
            delete rules.max;
          }
          if (rules.minlength != null && rules.maxlength != null) {
            rules.rangelength = [rules.minlength, rules.maxlength];
            delete rules.minlength;
            delete rules.maxlength;
          }
        }
        return rules;
      },
      // Converts a simple string to a {string: true} rule, e.g., "required" to {required:true}
      normalizeRule: function(data) {
        if (typeof data === "string") {
          var transformed = {};
          $2.each(data.split(/\s/), function() {
            transformed[this] = true;
          });
          data = transformed;
        }
        return data;
      },
      // http://jqueryvalidation.org/jQuery.validator.addMethod/
      addMethod: function(name, method, message) {
        $2.validator.methods[name] = method;
        $2.validator.messages[name] = message !== void 0 ? message : $2.validator.messages[name];
        if (method.length < 3) {
          $2.validator.addClassRules(name, $2.validator.normalizeRule(name));
        }
      },
      methods: {
        // http://jqueryvalidation.org/required-method/
        required: function(value2, element2, param) {
          if (!this.depend(param, element2)) {
            return "dependency-mismatch";
          }
          if (element2.nodeName.toLowerCase() === "select") {
            var val = $2(element2).val();
            return val && val.length > 0;
          }
          if (this.checkable(element2)) {
            return this.getLength(value2, element2) > 0;
          }
          return value2.length > 0;
        },
        // http://jqueryvalidation.org/email-method/
        email: function(value2, element2) {
          return this.optional(element2) || /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/.test(
            value2
          );
        },
        // http://jqueryvalidation.org/url-method/
        url: function(value2, element2) {
          return this.optional(element2) || /^(?:(?:(?:https?|ftp):)?\/\/)(?:\S+(?::\S*)?@)?(?:(?!(?:10|127)(?:\.\d{1,3}){3})(?!(?:169\.254|192\.168)(?:\.\d{1,3}){2})(?!172\.(?:1[6-9]|2\d|3[0-1])(?:\.\d{1,3}){2})(?:[1-9]\d?|1\d\d|2[01]\d|22[0-3])(?:\.(?:1?\d{1,2}|2[0-4]\d|25[0-5])){2}(?:\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4]))|(?:(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)(?:\.(?:[a-z\u00a1-\uffff0-9]-*)*[a-z\u00a1-\uffff0-9]+)*(?:\.(?:[a-z\u00a1-\uffff]{2,})).?)(?::\d{2,5})?(?:[/?#]\S*)?$/i.test(
            value2
          );
        },
        // http://jqueryvalidation.org/date-method/
        date: function(value2, element2) {
          return this.optional(element2) || !/Invalid|NaN/.test(new Date(value2).toString());
        },
        // http://jqueryvalidation.org/dateISO-method/
        dateISO: function(value2, element2) {
          return this.optional(element2) || /^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])$/.test(value2);
        },
        // http://jqueryvalidation.org/number-method/
        number: function(value2, element2) {
          return this.optional(element2) || /^(?:-?\d+|-?\d{1,3}(?:,\d{3})+)?(?:\.\d+)?$/.test(value2);
        },
        // http://jqueryvalidation.org/digits-method/
        digits: function(value2, element2) {
          return this.optional(element2) || /^\d+$/.test(value2);
        },
        // http://jqueryvalidation.org/creditcard-method/
        // based on http://en.wikipedia.org/wiki/Luhn_algorithm
        creditcard: function(value2, element2) {
          if (this.optional(element2)) {
            return "dependency-mismatch";
          }
          if (/[^0-9 \-]+/.test(value2)) {
            return false;
          }
          var nCheck = 0, nDigit = 0, bEven = false, n, cDigit;
          value2 = value2.replace(/\D/g, "");
          if (value2.length < 13 || value2.length > 19) {
            return false;
          }
          for (n = value2.length - 1; n >= 0; n--) {
            cDigit = value2.charAt(n);
            nDigit = parseInt(cDigit, 10);
            if (bEven) {
              if ((nDigit *= 2) > 9) {
                nDigit -= 9;
              }
            }
            nCheck += nDigit;
            bEven = !bEven;
          }
          return nCheck % 10 === 0;
        },
        // http://jqueryvalidation.org/minlength-method/
        minlength: function(value2, element2, param) {
          var length = Array.isArray(value2) ? value2.length : this.getLength(value2, element2);
          return this.optional(element2) || length >= param;
        },
        // http://jqueryvalidation.org/maxlength-method/
        maxlength: function(value2, element2, param) {
          var length = Array.isArray(value2) ? value2.length : this.getLength(value2, element2);
          return this.optional(element2) || length <= param;
        },
        // http://jqueryvalidation.org/rangelength-method/
        rangelength: function(value2, element2, param) {
          var length = Array.isArray(value2) ? value2.length : this.getLength(value2, element2);
          return this.optional(element2) || length >= param[0] && length <= param[1];
        },
        // http://jqueryvalidation.org/min-method/
        min: function(value2, element2, param) {
          return this.optional(element2) || value2 >= param;
        },
        // http://jqueryvalidation.org/max-method/
        max: function(value2, element2, param) {
          return this.optional(element2) || value2 <= param;
        },
        // http://jqueryvalidation.org/range-method/
        range: function(value2, element2, param) {
          return this.optional(element2) || value2 >= param[0] && value2 <= param[1];
        },
        // http://jqueryvalidation.org/equalTo-method/
        equalTo: function(value2, element2, param) {
          var target = $2(param);
          if (this.settings.onfocusout) {
            target.off(".validate-equalTo").on("blur.validate-equalTo", function() {
              $2(element2).valid();
            });
          }
          return value2 === target.val();
        },
        // http://jqueryvalidation.org/remote-method/
        remote: function(value2, element2, param) {
          if (this.optional(element2)) {
            return "dependency-mismatch";
          }
          var previous = this.previousValue(element2), validator, data;
          if (!this.settings.messages[element2.name]) {
            this.settings.messages[element2.name] = {};
          }
          previous.originalMessage = this.settings.messages[element2.name].remote;
          this.settings.messages[element2.name].remote = previous.message;
          param = typeof param === "string" && { url: param } || param;
          if (previous.old === value2) {
            return previous.valid;
          }
          previous.old = value2;
          validator = this;
          this.startRequest(element2);
          data = {};
          data[element2.name] = value2;
          $2.ajax(
            $2.extend(
              true,
              {
                mode: "abort",
                port: "validate" + element2.name,
                dataType: "json",
                data,
                context: validator.currentForm,
                success: function(response) {
                  var valid = response === true || response === "true", errors, message, submitted;
                  validator.settings.messages[element2.name].remote = previous.originalMessage;
                  if (valid) {
                    submitted = validator.formSubmitted;
                    validator.prepareElement(element2);
                    validator.formSubmitted = submitted;
                    validator.successList.push(element2);
                    delete validator.invalid[element2.name];
                    validator.showErrors();
                  } else {
                    errors = {};
                    message = response || validator.defaultMessage(element2, "remote");
                    errors[element2.name] = previous.message = typeof message === "function" ? message(value2) : message;
                    validator.invalid[element2.name] = true;
                    validator.showErrors(errors);
                  }
                  previous.valid = valid;
                  validator.stopRequest(element2, valid);
                }
              },
              param
            )
          );
          return "pending";
        }
      }
    });
    var pendingRequests = {}, ajax;
    if ($2.ajaxPrefilter) {
      $2.ajaxPrefilter(function(settings, _, xhr) {
        var port = settings.port;
        if (settings.mode === "abort") {
          if (pendingRequests[port]) {
            pendingRequests[port].abort();
          }
          pendingRequests[port] = xhr;
        }
      });
    } else {
      ajax = $2.ajax;
      $2.ajax = function(settings) {
        var mode = ("mode" in settings ? settings : $2.ajaxSettings).mode, port = ("port" in settings ? settings : $2.ajaxSettings).port;
        if (mode === "abort") {
          if (pendingRequests[port]) {
            pendingRequests[port].abort();
          }
          pendingRequests[port] = ajax.apply(this, arguments);
          return pendingRequests[port];
        }
        return ajax.apply(this, arguments);
      };
    }
  });
  function strlen(string) {
    var str = string + "";
    var i = 0, lgth = 0;
    if (!this.php_js || !this.php_js.ini || !this.php_js.ini["unicode.semantics"] || this.php_js.ini["unicode.semantics"].local_value.toLowerCase() !== "on") {
      return string.length;
    }
    var getWholeChar = function(str2, i2) {
      var code = str2.charCodeAt(i2);
      var next = "", prev = "";
      if (55296 <= code && code <= 56319) {
        if (str2.length <= i2 + 1) {
          throw "High surrogate without following low surrogate";
        }
        next = str2.charCodeAt(i2 + 1);
        if (56320 > next || next > 57343) {
          throw "High surrogate without following low surrogate";
        }
        return str2.charAt(i2) + str2.charAt(i2 + 1);
      } else if (56320 <= code && code <= 57343) {
        if (i2 === 0) {
          throw "Low surrogate without preceding high surrogate";
        }
        prev = str2.charCodeAt(i2 - 1);
        if (55296 > prev || prev > 56319) {
          throw "Low surrogate without preceding high surrogate";
        }
        return false;
      }
      return str2.charAt(i2);
    };
    for (i = 0, lgth = 0; i < str.length; i++) {
      if (getWholeChar(str, i) === false) {
        continue;
      }
      lgth++;
    }
    return lgth;
  }
  function array_diff(arr1) {
    var retArr = {}, argl = arguments.length, k1 = "", i = 1, k = "", arr = {};
    arr1keys: for (k1 in arr1) {
      for (i = 1; i < argl; i++) {
        arr = arguments[i];
        for (k in arr) {
          if (arr[k] === arr1[k1]) {
            continue arr1keys;
          }
        }
        retArr[k1] = arr1[k1];
      }
    }
    return retArr;
  }
  function strtotime(text, now) {
    var parsed, match, today, year, date, days, ranges, len, times, regex, i, fail = false;
    if (!text) {
      return fail;
    }
    text = text.replace(/^\s+|\s+$/g, "").replace(/\s{2,}/g, " ").replace(/[\t\r\n]/g, "").toLowerCase();
    match = text.match(
      /^(\d{1,4})([\-\.\/\:])(\d{1,2})([\-\.\/\:])(\d{1,4})(?:\s(\d{1,2}):(\d{2})?:?(\d{2})?)?(?:\s([A-Z]+)?)?$/
    );
    if (match && match[2] === match[4]) {
      if (match[1] > 1901) {
        switch (match[2]) {
          case "-": {
            if (match[3] > 12 || match[5] > 31) {
              return fail;
            }
            return new Date(
              match[1],
              parseInt(match[3], 10) - 1,
              match[5],
              match[6] || 0,
              match[7] || 0,
              match[8] || 0,
              match[9] || 0
            ) / 1e3;
          }
          case ".": {
            return fail;
          }
          case "/": {
            if (match[3] > 12 || match[5] > 31) {
              return fail;
            }
            return new Date(
              match[1],
              parseInt(match[3], 10) - 1,
              match[5],
              match[6] || 0,
              match[7] || 0,
              match[8] || 0,
              match[9] || 0
            ) / 1e3;
          }
        }
      } else if (match[5] > 1901) {
        switch (match[2]) {
          case "-": {
            if (match[3] > 12 || match[1] > 31) {
              return fail;
            }
            return new Date(
              match[5],
              parseInt(match[3], 10) - 1,
              match[1],
              match[6] || 0,
              match[7] || 0,
              match[8] || 0,
              match[9] || 0
            ) / 1e3;
          }
          case ".": {
            if (match[3] > 12 || match[1] > 31) {
              return fail;
            }
            return new Date(
              match[5],
              parseInt(match[3], 10) - 1,
              match[1],
              match[6] || 0,
              match[7] || 0,
              match[8] || 0,
              match[9] || 0
            ) / 1e3;
          }
          case "/": {
            if (match[1] > 12 || match[3] > 31) {
              return fail;
            }
            return new Date(
              match[5],
              parseInt(match[1], 10) - 1,
              match[3],
              match[6] || 0,
              match[7] || 0,
              match[8] || 0,
              match[9] || 0
            ) / 1e3;
          }
        }
      } else {
        switch (match[2]) {
          case "-": {
            if (match[3] > 12 || match[5] > 31 || match[1] < 70 && match[1] > 38) {
              return fail;
            }
            year = match[1] >= 0 && match[1] <= 38 ? +match[1] + 2e3 : match[1];
            return new Date(
              year,
              parseInt(match[3], 10) - 1,
              match[5],
              match[6] || 0,
              match[7] || 0,
              match[8] || 0,
              match[9] || 0
            ) / 1e3;
          }
          case ".": {
            if (match[5] >= 70) {
              if (match[3] > 12 || match[1] > 31) {
                return fail;
              }
              return new Date(
                match[5],
                parseInt(match[3], 10) - 1,
                match[1],
                match[6] || 0,
                match[7] || 0,
                match[8] || 0,
                match[9] || 0
              ) / 1e3;
            }
            if (match[5] < 60 && !match[6]) {
              if (match[1] > 23 || match[3] > 59) {
                return fail;
              }
              today = /* @__PURE__ */ new Date();
              return new Date(
                today.getFullYear(),
                today.getMonth(),
                today.getDate(),
                match[1] || 0,
                match[3] || 0,
                match[5] || 0,
                match[9] || 0
              ) / 1e3;
            }
            return fail;
          }
          case "/": {
            if (match[1] > 12 || match[3] > 31 || match[5] < 70 && match[5] > 38) {
              return fail;
            }
            year = match[5] >= 0 && match[5] <= 38 ? +match[5] + 2e3 : match[5];
            return new Date(
              year,
              parseInt(match[1], 10) - 1,
              match[3],
              match[6] || 0,
              match[7] || 0,
              match[8] || 0,
              match[9] || 0
            ) / 1e3;
          }
          case ":": {
            if (match[1] > 23 || match[3] > 59 || match[5] > 59) {
              return fail;
            }
            today = /* @__PURE__ */ new Date();
            return new Date(
              today.getFullYear(),
              today.getMonth(),
              today.getDate(),
              match[1] || 0,
              match[3] || 0,
              match[5] || 0
            ) / 1e3;
          }
        }
      }
    }
    if (text === "now") {
      return now === null || isNaN(now) ? (/* @__PURE__ */ new Date()).getTime() / 1e3 | 0 : now | 0;
    }
    if (!isNaN(parsed = Date.parse(text))) {
      return parsed / 1e3 | 0;
    }
    if (match = text.match(
      /^([0-9]{4}-[0-9]{2}-[0-9]{2})[ t]([0-9]{2}:[0-9]{2}:[0-9]{2}(\.[0-9]+)?)([\+-][0-9]{2}(:[0-9]{2})?|z)/
    )) {
      if (match[4] == "z") {
        match[4] = "Z";
      } else if (match[4].match(/^([\+-][0-9]{2})$/)) {
        match[4] = match[4] + ":00";
      }
      if (!isNaN(parsed = Date.parse(match[1] + "T" + match[2] + match[4]))) {
        return parsed / 1e3 | 0;
      }
    }
    date = now ? new Date(now * 1e3) : /* @__PURE__ */ new Date();
    days = {
      sun: 0,
      mon: 1,
      tue: 2,
      wed: 3,
      thu: 4,
      fri: 5,
      sat: 6
    };
    ranges = {
      yea: "FullYear",
      mon: "Month",
      day: "Date",
      hou: "Hours",
      min: "Minutes",
      sec: "Seconds"
    };
    function lastNext(type, range, modifier) {
      var diff, day = days[range];
      if (typeof day !== "undefined") {
        diff = day - date.getDay();
        if (diff === 0) {
          diff = 7 * modifier;
        } else if (diff > 0 && type === "last") {
          diff -= 7;
        } else if (diff < 0 && type === "next") {
          diff += 7;
        }
        date.setDate(date.getDate() + diff);
      }
    }
    function process(val) {
      var splt = val.split(" "), type = splt[0], range = splt[1].substring(0, 3), typeIsNumber = /\d+/.test(type), ago = splt[2] === "ago", num = (type === "last" ? -1 : 1) * (ago ? -1 : 1);
      if (typeIsNumber) {
        num *= parseInt(type, 10);
      }
      if (ranges.hasOwnProperty(range) && !splt[1].match(/^mon(day|\.)?$/i)) {
        return date["set" + ranges[range]](date["get" + ranges[range]]() + num);
      }
      if (range === "wee") {
        return date.setDate(date.getDate() + num * 7);
      }
      if (type === "next" || type === "last") {
        lastNext(type, range, num);
      } else if (!typeIsNumber) {
        return false;
      }
      return true;
    }
    times = "(years?|months?|weeks?|days?|hours?|minutes?|min|seconds?|sec|sunday|sun\\.?|monday|mon\\.?|tuesday|tue\\.?|wednesday|wed\\.?|thursday|thu\\.?|friday|fri\\.?|saturday|sat\\.?)";
    regex = "([+-]?\\d+\\s" + times + "|(last|next)\\s" + times + ")(\\sago)?";
    match = text.match(new RegExp(regex, "gi"));
    if (!match) {
      return fail;
    }
    for (i = 0, len = match.length; i < len; i++) {
      if (!process(match[i])) {
        return fail;
      }
    }
    return date.getTime() / 1e3;
  }
  function is_numeric(mixed_var) {
    var whitespace = " \n\r	\f\v            ​\u2028\u2029　";
    return (typeof mixed_var === "number" || typeof mixed_var === "string" && whitespace.indexOf(mixed_var.slice(-1)) === -1) && mixed_var !== "" && !isNaN(mixed_var);
  }
  (function(root, factory) {
    if (typeof define === "function" && define.amd) {
      define([], factory);
    } else {
      if (typeof module === "object" && module.exports) {
        module.exports = factory();
      } else {
        root.DateFormatter = factory();
      }
    }
  })(typeof self !== "undefined" ? self : void 0, function() {
    var DateFormatter2, $h;
    $h = {
      DAY: 1e3 * 60 * 60 * 24,
      HOUR: 3600,
      defaults: {
        dateSettings: {
          days: ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
          daysShort: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
          months: [
            "January",
            "February",
            "March",
            "April",
            "May",
            "June",
            "July",
            "August",
            "September",
            "October",
            "November",
            "December"
          ],
          monthsShort: ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
          meridiem: ["AM", "PM"],
          ordinal: function(number) {
            var n = number % 10, suffixes = { 1: "st", 2: "nd", 3: "rd" };
            return Math.floor(number % 100 / 10) === 1 || !suffixes[n] ? "th" : suffixes[n];
          }
        },
        separators: /[ \-+\/.T:@]/g,
        validParts: /[dDjlNSwzWFmMntLoYyaABgGhHisueTIOPZcrU]/g,
        intParts: /[djwNzmnyYhHgGis]/g,
        tzParts: /\b(?:[PMCEA][SDP]T|(?:Pacific|Mountain|Central|Eastern|Atlantic) (?:Standard|Daylight|Prevailing) Time|(?:GMT|UTC)(?:[-+]\d{4})?)\b/g,
        tzClip: /[^-+\dA-Z]/g
      },
      compare: function(str1, str2) {
        return typeof str1 === "string" && typeof str2 === "string" && str1.toLowerCase() === str2.toLowerCase();
      },
      lpad: function(value2, length, chr) {
        var val = value2.toString();
        chr = chr || "0";
        return val.length < length ? $h.lpad(chr + val, length) : val;
      },
      merge: function(out) {
        var i, obj;
        out = out || {};
        for (i = 1; i < arguments.length; i++) {
          obj = arguments[i];
          if (!obj) {
            continue;
          }
          for (var key in obj) {
            if (obj.hasOwnProperty(key)) {
              if (typeof obj[key] === "object") {
                $h.merge(out[key], obj[key]);
              } else {
                out[key] = obj[key];
              }
            }
          }
        }
        return out;
      },
      getIndex: function(val, arr) {
        for (var i = 0; i < arr.length; i++) {
          if (arr[i].toLowerCase() === val.toLowerCase()) {
            return i;
          }
        }
        return -1;
      }
    };
    DateFormatter2 = function(options) {
      var self2 = this, config = $h.merge($h.defaults, options);
      self2.dateSettings = config.dateSettings;
      self2.separators = config.separators;
      self2.validParts = config.validParts;
      self2.intParts = config.intParts;
      self2.tzParts = config.tzParts;
      self2.tzClip = config.tzClip;
    };
    DateFormatter2.prototype = {
      constructor: DateFormatter2,
      getMonth: function(val) {
        var self2 = this, i;
        i = $h.getIndex(val, self2.dateSettings.monthsShort) + 1;
        if (i === 0) {
          i = $h.getIndex(val, self2.dateSettings.months) + 1;
        }
        return i;
      },
      parseDate: function(vDate, vFormat) {
        var self2 = this, vFormatParts, vDateParts, i, vDateFlag = false, vTimeFlag = false, vDatePart, iDatePart, vSettings = self2.dateSettings, vMonth, vMeriIndex, vMeriOffset, len, mer, out = { date: null, year: null, month: null, day: null, hour: 0, min: 0, sec: 0 };
        if (!vDate) {
          return null;
        }
        if (vDate instanceof Date) {
          return vDate;
        }
        if (vFormat === "U") {
          i = parseInt(vDate);
          return i ? new Date(i * 1e3) : vDate;
        }
        switch (typeof vDate) {
          case "number":
            return new Date(vDate);
          case "string":
            break;
          default:
            return null;
        }
        vFormatParts = vFormat.match(self2.validParts);
        if (!vFormatParts || vFormatParts.length === 0) {
          throw new Error("Invalid date format definition.");
        }
        for (i = vFormatParts.length - 1; i >= 0; i--) {
          if (vFormatParts[i] === "S") {
            vFormatParts.splice(i, 1);
          }
        }
        vDateParts = vDate.replace(self2.separators, "\0").split("\0");
        for (i = 0; i < vDateParts.length; i++) {
          vDatePart = vDateParts[i];
          iDatePart = parseInt(vDatePart);
          switch (vFormatParts[i]) {
            case "y":
            case "Y":
              if (iDatePart) {
                len = vDatePart.length;
                out.year = len === 2 ? parseInt((iDatePart < 70 ? "20" : "19") + vDatePart) : iDatePart;
              } else {
                return null;
              }
              vDateFlag = true;
              break;
            case "m":
            case "n":
            case "M":
            case "F":
              if (isNaN(iDatePart)) {
                vMonth = self2.getMonth(vDatePart);
                if (vMonth > 0) {
                  out.month = vMonth;
                } else {
                  return null;
                }
              } else {
                if (iDatePart >= 1 && iDatePart <= 12) {
                  out.month = iDatePart;
                } else {
                  return null;
                }
              }
              vDateFlag = true;
              break;
            case "d":
            case "j":
              if (iDatePart >= 1 && iDatePart <= 31) {
                out.day = iDatePart;
              } else {
                return null;
              }
              vDateFlag = true;
              break;
            case "g":
            case "h":
              vMeriIndex = vFormatParts.indexOf("a") > -1 ? vFormatParts.indexOf("a") : vFormatParts.indexOf("A") > -1 ? vFormatParts.indexOf("A") : -1;
              mer = vDateParts[vMeriIndex];
              if (vMeriIndex !== -1) {
                vMeriOffset = $h.compare(mer, vSettings.meridiem[0]) ? 0 : $h.compare(mer, vSettings.meridiem[1]) ? 12 : -1;
                if (iDatePart >= 1 && iDatePart <= 12 && vMeriOffset !== -1) {
                  out.hour = iDatePart % 12 === 0 ? vMeriOffset : iDatePart + vMeriOffset;
                } else {
                  if (iDatePart >= 0 && iDatePart <= 23) {
                    out.hour = iDatePart;
                  }
                }
              } else {
                if (iDatePart >= 0 && iDatePart <= 23) {
                  out.hour = iDatePart;
                } else {
                  return null;
                }
              }
              vTimeFlag = true;
              break;
            case "G":
            case "H":
              if (iDatePart >= 0 && iDatePart <= 23) {
                out.hour = iDatePart;
              } else {
                return null;
              }
              vTimeFlag = true;
              break;
            case "i":
              if (iDatePart >= 0 && iDatePart <= 59) {
                out.min = iDatePart;
              } else {
                return null;
              }
              vTimeFlag = true;
              break;
            case "s":
              if (iDatePart >= 0 && iDatePart <= 59) {
                out.sec = iDatePart;
              } else {
                return null;
              }
              vTimeFlag = true;
              break;
          }
        }
        if (vDateFlag === true) {
          var varY = out.year || 0, varM = out.month ? out.month - 1 : 0, varD = out.day || 1;
          out.date = new Date(varY, varM, varD, out.hour, out.min, out.sec, 0);
        } else {
          if (vTimeFlag !== true) {
            return null;
          }
          out.date = new Date(0, 0, 0, out.hour, out.min, out.sec, 0);
        }
        return out.date;
      },
      guessDate: function(vDateStr, vFormat) {
        if (typeof vDateStr !== "string") {
          return vDateStr;
        }
        var self2 = this, vParts = vDateStr.replace(self2.separators, "\0").split("\0"), vPattern = /^[djmn]/g, len, vFormatParts = vFormat.match(self2.validParts), vDate = /* @__PURE__ */ new Date(), vDigit = 0, vYear, i, n, iPart, iSec;
        if (!vPattern.test(vFormatParts[0])) {
          return vDateStr;
        }
        for (i = 0; i < vParts.length; i++) {
          vDigit = 2;
          iPart = vParts[i];
          iSec = parseInt(iPart.substr(0, 2));
          if (isNaN(iSec)) {
            return null;
          }
          switch (i) {
            case 0:
              if (vFormatParts[0] === "m" || vFormatParts[0] === "n") {
                vDate.setMonth(iSec - 1);
              } else {
                vDate.setDate(iSec);
              }
              break;
            case 1:
              if (vFormatParts[0] === "m" || vFormatParts[0] === "n") {
                vDate.setDate(iSec);
              } else {
                vDate.setMonth(iSec - 1);
              }
              break;
            case 2:
              vYear = vDate.getFullYear();
              len = iPart.length;
              vDigit = len < 4 ? len : 4;
              vYear = parseInt(len < 4 ? vYear.toString().substr(0, 4 - len) + iPart : iPart.substr(0, 4));
              if (!vYear) {
                return null;
              }
              vDate.setFullYear(vYear);
              break;
            case 3:
              vDate.setHours(iSec);
              break;
            case 4:
              vDate.setMinutes(iSec);
              break;
            case 5:
              vDate.setSeconds(iSec);
              break;
          }
          n = iPart.substr(vDigit);
          if (n.length > 0) {
            vParts.splice(i + 1, 0, n);
          }
        }
        return vDate;
      },
      parseFormat: function(vChar, vDate) {
        var self2 = this, vSettings = self2.dateSettings, fmt, backslash = /\\?(.?)/gi, doFormat = function(t, s) {
          return fmt[t] ? fmt[t]() : s;
        };
        fmt = {
          /////////
          // DAY //
          /////////
          /**
           * Day of month with leading 0: `01..31`
           * @return {string}
           */
          d: function() {
            return $h.lpad(fmt.j(), 2);
          },
          /**
           * Shorthand day name: `Mon...Sun`
           * @return {string}
           */
          D: function() {
            return vSettings.daysShort[fmt.w()];
          },
          /**
           * Day of month: `1..31`
           * @return {number}
           */
          j: function() {
            return vDate.getDate();
          },
          /**
           * Full day name: `Monday...Sunday`
           * @return {string}
           */
          l: function() {
            return vSettings.days[fmt.w()];
          },
          /**
           * ISO-8601 day of week: `1[Mon]..7[Sun]`
           * @return {number}
           */
          N: function() {
            return fmt.w() || 7;
          },
          /**
           * Day of week: `0[Sun]..6[Sat]`
           * @return {number}
           */
          w: function() {
            return vDate.getDay();
          },
          /**
           * Day of year: `0..365`
           * @return {number}
           */
          z: function() {
            var a = new Date(fmt.Y(), fmt.n() - 1, fmt.j()), b = new Date(fmt.Y(), 0, 1);
            return Math.round((a - b) / $h.DAY);
          },
          //////////
          // WEEK //
          //////////
          /**
           * ISO-8601 week number
           * @return {number}
           */
          W: function() {
            var a = new Date(fmt.Y(), fmt.n() - 1, fmt.j() - fmt.N() + 3), b = new Date(a.getFullYear(), 0, 4);
            return $h.lpad(1 + Math.round((a - b) / $h.DAY / 7), 2);
          },
          ///////////
          // MONTH //
          ///////////
          /**
           * Full month name: `January...December`
           * @return {string}
           */
          F: function() {
            return vSettings.months[vDate.getMonth()];
          },
          /**
           * Month w/leading 0: `01..12`
           * @return {string}
           */
          m: function() {
            return $h.lpad(fmt.n(), 2);
          },
          /**
           * Shorthand month name; `Jan...Dec`
           * @return {string}
           */
          M: function() {
            return vSettings.monthsShort[vDate.getMonth()];
          },
          /**
           * Month: `1...12`
           * @return {number}
           */
          n: function() {
            return vDate.getMonth() + 1;
          },
          /**
           * Days in month: `28...31`
           * @return {number}
           */
          t: function() {
            return new Date(fmt.Y(), fmt.n(), 0).getDate();
          },
          //////////
          // YEAR //
          //////////
          /**
           * Is leap year? `0 or 1`
           * @return {number}
           */
          L: function() {
            var Y = fmt.Y();
            return Y % 4 === 0 && Y % 100 !== 0 || Y % 400 === 0 ? 1 : 0;
          },
          /**
           * ISO-8601 year
           * @return {number}
           */
          o: function() {
            var n = fmt.n(), W = fmt.W(), Y = fmt.Y();
            return Y + (n === 12 && W < 9 ? 1 : n === 1 && W > 9 ? -1 : 0);
          },
          /**
           * Full year: `e.g. 1980...2010`
           * @return {number}
           */
          Y: function() {
            return vDate.getFullYear();
          },
          /**
           * Last two digits of year: `00...99`
           * @return {string}
           */
          y: function() {
            return fmt.Y().toString().slice(-2);
          },
          //////////
          // TIME //
          //////////
          /**
           * Meridian lower: `am or pm`
           * @return {string}
           */
          a: function() {
            return fmt.A().toLowerCase();
          },
          /**
           * Meridian upper: `AM or PM`
           * @return {string}
           */
          A: function() {
            var n = fmt.G() < 12 ? 0 : 1;
            return vSettings.meridiem[n];
          },
          /**
           * Swatch Internet time: `000..999`
           * @return {string}
           */
          B: function() {
            var H = vDate.getUTCHours() * $h.HOUR, i = vDate.getUTCMinutes() * 60, s = vDate.getUTCSeconds();
            return $h.lpad(Math.floor((H + i + s + $h.HOUR) / 86.4) % 1e3, 3);
          },
          /**
           * 12-Hours: `1..12`
           * @return {number}
           */
          g: function() {
            return fmt.G() % 12 || 12;
          },
          /**
           * 24-Hours: `0..23`
           * @return {number}
           */
          G: function() {
            return vDate.getHours();
          },
          /**
           * 12-Hours with leading 0: `01..12`
           * @return {string}
           */
          h: function() {
            return $h.lpad(fmt.g(), 2);
          },
          /**
           * 24-Hours w/leading 0: `00..23`
           * @return {string}
           */
          H: function() {
            return $h.lpad(fmt.G(), 2);
          },
          /**
           * Minutes w/leading 0: `00..59`
           * @return {string}
           */
          i: function() {
            return $h.lpad(vDate.getMinutes(), 2);
          },
          /**
           * Seconds w/leading 0: `00..59`
           * @return {string}
           */
          s: function() {
            return $h.lpad(vDate.getSeconds(), 2);
          },
          /**
           * Microseconds: `000000-999000`
           * @return {string}
           */
          u: function() {
            return $h.lpad(vDate.getMilliseconds() * 1e3, 6);
          },
          //////////////
          // TIMEZONE //
          //////////////
          /**
           * Timezone identifier: `e.g. Atlantic/Azores, ...`
           * @return {string}
           */
          e: function() {
            var str = /\((.*)\)/.exec(String(vDate))[1];
            return str || "Coordinated Universal Time";
          },
          /**
           * DST observed? `0 or 1`
           * @return {number}
           */
          I: function() {
            var a = new Date(fmt.Y(), 0), c = Date.UTC(fmt.Y(), 0), b = new Date(fmt.Y(), 6), d = Date.UTC(fmt.Y(), 6);
            return a - c !== b - d ? 1 : 0;
          },
          /**
           * Difference to GMT in hour format: `e.g. +0200`
           * @return {string}
           */
          O: function() {
            var tzo = vDate.getTimezoneOffset(), a = Math.abs(tzo);
            return (tzo > 0 ? "-" : "+") + $h.lpad(Math.floor(a / 60) * 100 + a % 60, 4);
          },
          /**
           * Difference to GMT with colon: `e.g. +02:00`
           * @return {string}
           */
          P: function() {
            var O = fmt.O();
            return O.substr(0, 3) + ":" + O.substr(3, 2);
          },
          /**
           * Timezone abbreviation: `e.g. EST, MDT, ...`
           * @return {string}
           */
          T: function() {
            var str = (String(vDate).match(self2.tzParts) || [""]).pop().replace(self2.tzClip, "");
            return str || "UTC";
          },
          /**
           * Timezone offset in seconds: `-43200...50400`
           * @return {number}
           */
          Z: function() {
            return -vDate.getTimezoneOffset() * 60;
          },
          ////////////////////
          // FULL DATE TIME //
          ////////////////////
          /**
           * ISO-8601 date
           * @return {string}
           */
          c: function() {
            return "Y-m-d\\TH:i:sP".replace(backslash, doFormat);
          },
          /**
           * RFC 2822 date
           * @return {string}
           */
          r: function() {
            return "D, d M Y H:i:s O".replace(backslash, doFormat);
          },
          /**
           * Seconds since UNIX epoch
           * @return {number}
           */
          U: function() {
            return vDate.getTime() / 1e3 || 0;
          }
        };
        return doFormat(vChar, vChar);
      },
      formatDate: function(vDate, vFormat) {
        var self2 = this, i, n, len, str, vChar, vDateStr = "", BACKSLASH = "\\";
        if (typeof vDate === "string") {
          vDate = self2.parseDate(vDate, vFormat);
          if (!vDate) {
            return null;
          }
        }
        if (vDate instanceof Date) {
          len = vFormat.length;
          for (i = 0; i < len; i++) {
            vChar = vFormat.charAt(i);
            if (vChar === "S" || vChar === BACKSLASH) {
              continue;
            }
            if (i > 0 && vFormat.charAt(i - 1) === BACKSLASH) {
              vDateStr += vChar;
              continue;
            }
            str = self2.parseFormat(vChar, vDate);
            if (i !== len - 1 && self2.intParts.test(vChar) && vFormat.charAt(i + 1) === "S") {
              n = parseInt(str) || 0;
              str += self2.dateSettings.ordinal(n);
            }
            vDateStr += str;
          }
          return vDateStr;
        }
        return "";
      }
    };
    return DateFormatter2;
  });
  const laravelValidation = {
    implicitRules: ["Required", "Confirmed"],
    /**
     * Initialize laravel validations.
     */
    init: function() {
      $.validator.classRuleSettings = {};
      $.validator.attributeRules = function() {
        this.rules = {};
      };
      $.validator.dataRules = this.arrayRules;
      $.validator.prototype.arrayRulesCache = {};
      this.setupValidations();
    },
    arrayRules: function(element2) {
      const rules = {}, validator = $.data(element2.form, "validator"), cache = validator.arrayRulesCache;
      if (element2.name.indexOf("[") === -1) {
        return rules;
      }
      if (!(element2.name in cache)) {
        cache[element2.name] = {};
      }
      $.each(validator.settings.rules, function(name, tmpRules) {
        if (name in cache[element2.name]) {
          $.extend(rules, cache[element2.name][name]);
        } else {
          cache[element2.name][name] = {};
          const nameRegExp = laravelValidation.helpers.regexFromWildcard(name);
          if (element2.name.match(nameRegExp)) {
            const newRules = $.validator.normalizeRule(tmpRules) || {};
            cache[element2.name][name] = newRules;
            $.extend(rules, newRules);
          }
        }
      });
      return rules;
    },
    setupValidations: function() {
      $.validator.addMethod(
        "laravelValidation",
        function(value2, element2, params2) {
          const validator = this;
          let validated = true;
          const previous = this.previousValue(element2);
          const rules = [];
          $.each(params2, function(i, param) {
            if (param[3] || laravelValidation.implicitRules.indexOf(param[0]) !== -1) {
              rules.unshift(param);
            } else {
              rules.push(param);
            }
          });
          $.each(rules, function(i, param) {
            const implicit = param[3] || laravelValidation.implicitRules.indexOf(param[0]) !== -1;
            const rule = param[0];
            const message = param[2];
            if (!implicit && validator.optional(element2)) {
              validated = "dependency-mismatch";
              return false;
            }
            if (laravelValidation.methods[rule] !== void 0) {
              validated = laravelValidation.methods[rule].call(
                validator,
                value2,
                element2,
                param[1],
                function(valid) {
                  validator.settings.messages[element2.name].laravelValidationRemote = previous.originalMessage;
                  if (valid) {
                    const submitted = validator.formSubmitted;
                    validator.prepareElement(element2);
                    validator.formSubmitted = submitted;
                    validator.successList.push(element2);
                    delete validator.invalid[element2.name];
                    validator.showErrors();
                  } else {
                    const errors = {};
                    errors[element2.name] = previous.message = typeof message === "function" ? message(value2) : message;
                    validator.invalid[element2.name] = true;
                    validator.showErrors(errors);
                  }
                  validator.showErrors(validator.errorMap);
                  previous.valid = valid;
                }
              );
            } else {
              validated = false;
            }
            if (validated !== true) {
              if (!validator.settings.messages[element2.name]) {
                validator.settings.messages[element2.name] = {};
              }
              validator.settings.messages[element2.name].laravelValidation = message;
              return false;
            }
          });
          return validated;
        },
        ""
      );
      $.validator.addMethod(
        "laravelValidationRemote",
        function(value2, element2, params2) {
          const validator = this;
          const previous = this.previousValue(element2);
          let implicit = false, check = params2[0][1], attribute = element2.name, token = check[1], validateAll = check[2];
          $.each(params2, function(i, parameters) {
            implicit = implicit || parameters[3];
          });
          if (!implicit && validator.optional(element2)) {
            return "dependency-mismatch";
          }
          let data;
          if (!validator.settings.messages[element2.name]) {
            validator.settings.messages[element2.name] = {};
          }
          previous.originalMessage = validator.settings.messages[element2.name].laravelValidationRemote;
          validator.settings.messages[element2.name].laravelValidationRemote = previous.message;
          var param = typeof param === "string" && { url: param } || param;
          if (laravelValidation.helpers.arrayEquals(previous.old, value2) || previous.old === value2) {
            return previous.valid;
          }
          previous.old = value2;
          data = $(validator.currentForm).serializeArray();
          data.push({
            name: "_js_validation",
            value: attribute
          });
          data.push({
            name: "_js_validation_validate_all",
            value: validateAll
          });
          let formMethod = $(validator.currentForm).attr("method");
          validator.startRequest(element2);
          $.ajax(
            $.extend(
              true,
              {
                mode: "abort",
                port: "validate" + element2.name,
                dataType: "json",
                data,
                context: validator.currentForm,
                url: $(validator.currentForm).attr("action"),
                type: formMethod,
                beforeSend: function(xhr) {
                  if ($(validator.currentForm).attr("method").toLowerCase() !== "get" && token) {
                    return xhr.setRequestHeader("X-XSRF-TOKEN", token);
                  }
                },
                complete: function() {
                  $(validator.currentForm).find("button[type=submit]").prop("disabled", false).removeClass("disabled");
                }
              },
              param || {}
            )
          ).always(function(response, textStatus) {
            let errors, message, submitted, valid;
            if (textStatus === "error") {
              valid = false;
              response = laravelValidation.helpers.parseErrorResponse(response);
            } else if (textStatus === "success") {
              valid = response === true || response === "true";
            } else {
              return;
            }
            validator.settings.messages[element2.name].laravelValidationRemote = previous.originalMessage;
            if (valid) {
              submitted = validator.formSubmitted;
              validator.prepareElement(element2);
              validator.formSubmitted = submitted;
              validator.successList.push(element2);
              delete validator.invalid[element2.name];
              validator.showErrors();
            } else {
              errors = {};
              message = response || validator.defaultMessage(element2, "remote");
              errors[element2.name] = previous.message = typeof message === "function" ? message(value2) : message[0];
              validator.invalid[element2.name] = true;
              validator.showErrors(errors);
            }
            validator.showErrors(validator.errorMap);
            previous.valid = valid;
            validator.stopRequest(element2, valid);
          });
          return "pending";
        },
        ""
      );
    }
  };
  $(() => {
    laravelValidation.init();
  });
  $.extend(true, laravelValidation, {
    helpers: {
      /**
       * Numeric rules
       */
      numericRules: ["Integer", "Numeric"],
      /**
       * Gets the file information from file input.
       *
       * @param fieldObj
       * @param index
       * @returns {{file: *, extension: string, size: number}}
       */
      fileinfo: function(fieldObj, index) {
        var FileName = fieldObj.value;
        index = typeof index !== "undefined" ? index : 0;
        if (fieldObj.files !== null) {
          if (typeof fieldObj.files[index] !== "undefined") {
            return {
              file: FileName,
              extension: FileName.substr(FileName.lastIndexOf(".") + 1),
              size: fieldObj.files[index].size / 1024,
              type: fieldObj.files[index].type
            };
          }
        }
        return false;
      },
      /**
       * Gets the selectors for th specified field names.
       *
       * @param names
       * @returns {string}
       */
      selector: function(names) {
        var selector = [];
        if (!Array.isArray(names)) {
          names = [names];
        }
        for (var i = 0; i < names.length; i++) {
          selector.push("[name='" + names[i] + "']");
        }
        return selector.join();
      },
      /**
       * Check if element has numeric rules.
       *
       * @param element
       * @returns {boolean}
       */
      hasNumericRules: function(element2) {
        return this.hasRules(element2, this.numericRules);
      },
      /**
       * Check if element has passed rules.
       *
       * @param element
       * @param rules
       * @returns {boolean}
       */
      hasRules: function(element2, rules) {
        var found = false;
        if (typeof rules === "string") {
          rules = [rules];
        }
        var validator = $.data(element2.form, "validator");
        var listRules = [];
        var cache = validator.arrayRulesCache;
        if (element2.name in cache) {
          $.each(cache[element2.name], function(index, arrayRule) {
            listRules.push(arrayRule);
          });
        }
        if (element2.name in validator.settings.rules) {
          listRules.push(validator.settings.rules[element2.name]);
        }
        $.each(listRules, function(index, objRules) {
          if ("laravelValidation" in objRules) {
            var _rules = objRules.laravelValidation;
            for (var i = 0; i < _rules.length; i++) {
              if ($.inArray(_rules[i][0], rules) !== -1) {
                found = true;
                return false;
              }
            }
          }
        });
        return found;
      },
      /**
       * Return the string length using PHP function.
       * http://php.net/manual/en/function.strlen.php
       * http://phpjs.org/functions/strlen/
       *
       * @param string
       */
      strlen: function(string) {
        return strlen(string);
      },
      /**
       * Get the size of the object depending of his type.
       *
       * @param obj
       * @param element
       * @param value
       * @returns int
       */
      getSize: function getSize(obj, element2, value2) {
        if (this.hasNumericRules(element2) && this.is_numeric(value2)) {
          return parseFloat(value2);
        } else if (Array.isArray(value2)) {
          return parseFloat(value2.length);
        } else if (element2.type === "file") {
          return parseFloat(Math.floor(this.fileinfo(element2).size));
        }
        return parseFloat(this.strlen(value2));
      },
      /**
       * Return specified rule from element.
       *
       * @param rule
       * @param element
       * @returns object
       */
      getLaravelValidation: function(rule, element2) {
        var found = void 0;
        $.each($.validator.staticRules(element2), function(key, rules) {
          if (key === "laravelValidation") {
            $.each(rules, function(i, value2) {
              if (value2[0] === rule) {
                found = value2;
              }
            });
          }
        });
        return found;
      },
      /**
       * Return he timestamp of value passed using format or default format in element.
       *
       * @param value
       * @param format
       * @returns {boolean|int}
       */
      parseTime: function(value2, format) {
        var timeValue = false;
        var fmt = new DateFormatter();
        if (typeof value2 === "number" && typeof format === "undefined") {
          return value2;
        }
        if ($.type(format) === "object") {
          var dateRule = this.getLaravelValidation("DateFormat", format);
          if (dateRule !== void 0) {
            format = dateRule[1][0];
          } else {
            format = null;
          }
        }
        if (format == null) {
          timeValue = this.strtotime(value2);
        } else {
          timeValue = fmt.parseDate(value2, format);
          if (timeValue) {
            timeValue = Math.round(timeValue.getTime() / 1e3);
          }
        }
        return timeValue;
      },
      /**
       * This method allows you to intelligently guess the date by closely matching the specific format.
       *
       * @param value
       * @param format
       * @returns {Date}
       */
      guessDate: function(value2, format) {
        var fmt = new DateFormatter();
        return fmt.guessDate(value2, format);
      },
      /**
       * Returns Unix timestamp based on PHP function strototime.
       * http://php.net/manual/es/function.strtotime.php
       * http://phpjs.org/functions/strtotime/
       *
       * @param text
       * @param now
       * @returns {*}
       */
      strtotime: function(text, now) {
        return strtotime(text, now);
      },
      /**
       * Returns if value is numeric.
       * http://php.net/manual/es/var.is_numeric.php
       * http://phpjs.org/functions/is_numeric/
       *
       * @param mixed_var
       * @returns {*}
       */
      is_numeric: function(mixed_var) {
        return is_numeric(mixed_var);
      },
      /**
       * Returns Array diff based on PHP function array_diff.
       * http://php.net/manual/es/function.array_diff.php
       * http://phpjs.org/functions/array_diff/
       *
       * @param arr1
       * @param arr2
       * @returns {*}
       */
      arrayDiff: function(arr1, arr2) {
        return array_diff(arr1, arr2);
      },
      /**
       * Check whether two arrays are equal to one another.
       *
       * @param arr1
       * @param arr2
       * @returns {*}
       */
      arrayEquals: function(arr1, arr2) {
        if (!Array.isArray(arr1) || !Array.isArray(arr2)) {
          return false;
        }
        if (arr1.length !== arr2.length) {
          return false;
        }
        return $.isEmptyObject(this.arrayDiff(arr1, arr2));
      },
      /**
       * Makes element dependant from other.
       *
       * @param validator
       * @param element
       * @param name
       * @returns {*}
       */
      dependentElement: function(validator, element2, name) {
        var el = validator.findByName(name);
        if (el[0] !== void 0 && validator.settings.onfocusout) {
          var event = "blur";
          if (el[0].tagName === "SELECT" || el[0].tagName === "OPTION" || el[0].type === "checkbox" || el[0].type === "radio") {
            event = "click";
          }
          var ruleName = ".validate-laravelValidation";
          el.off(ruleName).off(event + ruleName + "-" + element2.name).on(event + ruleName + "-" + element2.name, function() {
            $(element2).valid();
          });
        }
        return el[0];
      },
      /**
       * Parses error Ajax response and gets the message.
       *
       * @param response
       * @returns {string[]}
       */
      parseErrorResponse: function(response) {
        var newResponse = ["Whoops, looks like something went wrong."];
        if ("responseText" in response) {
          var errorMsg = response.responseText.match(/<h1\s*>(.*)<\/h1\s*>/i);
          if (Array.isArray(errorMsg)) {
            newResponse = [errorMsg[1]];
          }
        }
        return newResponse;
      },
      /**
       * Escape string to use as Regular Expression.
       *
       * @param str
       * @returns string
       */
      escapeRegExp: function(str) {
        return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
      },
      /**
       * Generate RegExp from wildcard attributes.
       *
       * @param name
       * @returns {RegExp}
       */
      regexFromWildcard: function(name) {
        var nameParts = name.split("[*]");
        if (nameParts.length === 1) {
          nameParts.push("");
        }
        var regexpParts = nameParts.map(function(currentValue, index) {
          if (index % 2 === 0) {
            currentValue = currentValue + "[";
          } else {
            currentValue = "]" + currentValue;
          }
          return laravelValidation.helpers.escapeRegExp(currentValue);
        });
        return new RegExp("^" + regexpParts.join("[^\\]]*") + "$");
      }
    }
  });
  $.extend(true, laravelValidation, {
    helpers: {
      /**
       * Check if the specified timezone is valid.
       *
       * @param value
       * @returns {boolean}
       */
      isTimezone: function(value2) {
        var timezones = {
          africa: [
            "abidjan",
            "accra",
            "addis_ababa",
            "algiers",
            "asmara",
            "bamako",
            "bangui",
            "banjul",
            "bissau",
            "blantyre",
            "brazzaville",
            "bujumbura",
            "cairo",
            "casablanca",
            "ceuta",
            "conakry",
            "dakar",
            "dar_es_salaam",
            "djibouti",
            "douala",
            "el_aaiun",
            "freetown",
            "gaborone",
            "harare",
            "johannesburg",
            "juba",
            "kampala",
            "khartoum",
            "kigali",
            "kinshasa",
            "lagos",
            "libreville",
            "lome",
            "luanda",
            "lubumbashi",
            "lusaka",
            "malabo",
            "maputo",
            "maseru",
            "mbabane",
            "mogadishu",
            "monrovia",
            "nairobi",
            "ndjamena",
            "niamey",
            "nouakchott",
            "ouagadougou",
            "porto-novo",
            "sao_tome",
            "tripoli",
            "tunis",
            "windhoek"
          ],
          america: [
            "adak",
            "anchorage",
            "anguilla",
            "antigua",
            "araguaina",
            "argentina/buenos_aires",
            "argentina/catamarca",
            "argentina/cordoba",
            "argentina/jujuy",
            "argentina/la_rioja",
            "argentina/mendoza",
            "argentina/rio_gallegos",
            "argentina/salta",
            "argentina/san_juan",
            "argentina/san_luis",
            "argentina/tucuman",
            "argentina/ushuaia",
            "aruba",
            "asuncion",
            "atikokan",
            "bahia",
            "bahia_banderas",
            "barbados",
            "belem",
            "belize",
            "blanc-sablon",
            "boa_vista",
            "bogota",
            "boise",
            "cambridge_bay",
            "campo_grande",
            "cancun",
            "caracas",
            "cayenne",
            "cayman",
            "chicago",
            "chihuahua",
            "costa_rica",
            "creston",
            "cuiaba",
            "curacao",
            "danmarkshavn",
            "dawson",
            "dawson_creek",
            "denver",
            "detroit",
            "dominica",
            "edmonton",
            "eirunepe",
            "el_salvador",
            "fortaleza",
            "glace_bay",
            "godthab",
            "goose_bay",
            "grand_turk",
            "grenada",
            "guadeloupe",
            "guatemala",
            "guayaquil",
            "guyana",
            "halifax",
            "havana",
            "hermosillo",
            "indiana/indianapolis",
            "indiana/knox",
            "indiana/marengo",
            "indiana/petersburg",
            "indiana/tell_city",
            "indiana/vevay",
            "indiana/vincennes",
            "indiana/winamac",
            "inuvik",
            "iqaluit",
            "jamaica",
            "juneau",
            "kentucky/louisville",
            "kentucky/monticello",
            "kralendijk",
            "la_paz",
            "lima",
            "los_angeles",
            "lower_princes",
            "maceio",
            "managua",
            "manaus",
            "marigot",
            "martinique",
            "matamoros",
            "mazatlan",
            "menominee",
            "merida",
            "metlakatla",
            "mexico_city",
            "miquelon",
            "moncton",
            "monterrey",
            "montevideo",
            "montreal",
            "montserrat",
            "nassau",
            "new_york",
            "nipigon",
            "nome",
            "noronha",
            "north_dakota/beulah",
            "north_dakota/center",
            "north_dakota/new_salem",
            "ojinaga",
            "panama",
            "pangnirtung",
            "paramaribo",
            "phoenix",
            "port-au-prince",
            "port_of_spain",
            "porto_velho",
            "puerto_rico",
            "rainy_river",
            "rankin_inlet",
            "recife",
            "regina",
            "resolute",
            "rio_branco",
            "santa_isabel",
            "santarem",
            "santiago",
            "santo_domingo",
            "sao_paulo",
            "scoresbysund",
            "shiprock",
            "sitka",
            "st_barthelemy",
            "st_johns",
            "st_kitts",
            "st_lucia",
            "st_thomas",
            "st_vincent",
            "swift_current",
            "tegucigalpa",
            "thule",
            "thunder_bay",
            "tijuana",
            "toronto",
            "tortola",
            "vancouver",
            "whitehorse",
            "winnipeg",
            "yakutat",
            "yellowknife"
          ],
          antarctica: [
            "casey",
            "davis",
            "dumontdurville",
            "macquarie",
            "mawson",
            "mcmurdo",
            "palmer",
            "rothera",
            "south_pole",
            "syowa",
            "vostok"
          ],
          arctic: ["longyearbyen"],
          asia: [
            "aden",
            "almaty",
            "amman",
            "anadyr",
            "aqtau",
            "aqtobe",
            "ashgabat",
            "baghdad",
            "bahrain",
            "baku",
            "bangkok",
            "beirut",
            "bishkek",
            "brunei",
            "choibalsan",
            "chongqing",
            "colombo",
            "damascus",
            "dhaka",
            "dili",
            "dubai",
            "dushanbe",
            "gaza",
            "harbin",
            "hebron",
            "ho_chi_minh",
            "hong_kong",
            "hovd",
            "irkutsk",
            "jakarta",
            "jayapura",
            "jerusalem",
            "kabul",
            "kamchatka",
            "karachi",
            "kashgar",
            "kathmandu",
            "khandyga",
            "kolkata",
            "krasnoyarsk",
            "kuala_lumpur",
            "kuching",
            "kuwait",
            "macau",
            "magadan",
            "makassar",
            "manila",
            "muscat",
            "nicosia",
            "novokuznetsk",
            "novosibirsk",
            "omsk",
            "oral",
            "phnom_penh",
            "pontianak",
            "pyongyang",
            "qatar",
            "qyzylorda",
            "rangoon",
            "riyadh",
            "sakhalin",
            "samarkand",
            "seoul",
            "shanghai",
            "singapore",
            "taipei",
            "tashkent",
            "tbilisi",
            "tehran",
            "thimphu",
            "tokyo",
            "ulaanbaatar",
            "urumqi",
            "ust-nera",
            "vientiane",
            "vladivostok",
            "yakutsk",
            "yekaterinburg",
            "yerevan"
          ],
          atlantic: [
            "azores",
            "bermuda",
            "canary",
            "cape_verde",
            "faroe",
            "madeira",
            "reykjavik",
            "south_georgia",
            "st_helena",
            "stanley"
          ],
          australia: [
            "adelaide",
            "brisbane",
            "broken_hill",
            "currie",
            "darwin",
            "eucla",
            "hobart",
            "lindeman",
            "lord_howe",
            "melbourne",
            "perth",
            "sydney"
          ],
          europe: [
            "amsterdam",
            "andorra",
            "athens",
            "belgrade",
            "berlin",
            "bratislava",
            "brussels",
            "bucharest",
            "budapest",
            "busingen",
            "chisinau",
            "copenhagen",
            "dublin",
            "gibraltar",
            "guernsey",
            "helsinki",
            "isle_of_man",
            "istanbul",
            "jersey",
            "kaliningrad",
            "kiev",
            "lisbon",
            "ljubljana",
            "london",
            "luxembourg",
            "madrid",
            "malta",
            "mariehamn",
            "minsk",
            "monaco",
            "moscow",
            "oslo",
            "paris",
            "podgorica",
            "prague",
            "riga",
            "rome",
            "samara",
            "san_marino",
            "sarajevo",
            "simferopol",
            "skopje",
            "sofia",
            "stockholm",
            "tallinn",
            "tirane",
            "uzhgorod",
            "vaduz",
            "vatican",
            "vienna",
            "vilnius",
            "volgograd",
            "warsaw",
            "zagreb",
            "zaporozhye",
            "zurich"
          ],
          indian: [
            "antananarivo",
            "chagos",
            "christmas",
            "cocos",
            "comoro",
            "kerguelen",
            "mahe",
            "maldives",
            "mauritius",
            "mayotte",
            "reunion"
          ],
          pacific: [
            "apia",
            "auckland",
            "chatham",
            "chuuk",
            "easter",
            "efate",
            "enderbury",
            "fakaofo",
            "fiji",
            "funafuti",
            "galapagos",
            "gambier",
            "guadalcanal",
            "guam",
            "honolulu",
            "johnston",
            "kiritimati",
            "kosrae",
            "kwajalein",
            "majuro",
            "marquesas",
            "midway",
            "nauru",
            "niue",
            "norfolk",
            "noumea",
            "pago_pago",
            "palau",
            "pitcairn",
            "pohnpei",
            "port_moresby",
            "rarotonga",
            "saipan",
            "tahiti",
            "tarawa",
            "tongatapu",
            "wake",
            "wallis"
          ],
          utc: [""]
        };
        var tzparts = value2.split("/", 2);
        var continent = tzparts[0].toLowerCase();
        var city = "";
        if (tzparts[1]) {
          city = tzparts[1].toLowerCase();
        }
        return continent in timezones && (timezones[continent].length === 0 || timezones[continent].indexOf(city) !== -1);
      }
    }
  });
  $.extend(true, laravelValidation, {
    methods: {
      helpers: laravelValidation.helpers,
      jsRemoteTimer: 0,
      /**
       * "Validate" optional attributes.
       * Always returns true, just lets us put sometimes in rules.
       *
       * @return {boolean}
       */
      Sometimes: function() {
        return true;
      },
      /**
       * Bail This is the default behaivour os JSValidation.
       * Always returns true, just lets us put sometimes in rules.
       *
       * @return {boolean}
       */
      Bail: function() {
        return true;
      },
      /**
       * "Indicate" validation should pass if value is null.
       * Always returns true, just lets us put "nullable" in rules.
       *
       * @return {boolean}
       */
      Nullable: function() {
        return true;
      },
      /**
       * Validate the given attribute is filled if it is present.
       */
      Filled: function(value2, element2) {
        return $.validator.methods.required.call(this, value2, element2, true);
      },
      /**
       * Validate that a required attribute exists.
       */
      Required: function(value2, element2) {
        return $.validator.methods.required.call(this, value2, element2);
      },
      /**
       * Validate that an attribute exists when any other attribute exists.
       *
       * @return {boolean}
       */
      RequiredWith: function(value2, element2, params2) {
        var validator = this, required = false;
        var currentObject = this;
        $.each(params2, function(i, param) {
          var target = laravelValidation.helpers.dependentElement(currentObject, element2, param);
          required = required || target !== void 0 && $.validator.methods.required.call(validator, currentObject.elementValue(target), target, true);
        });
        if (required) {
          return $.validator.methods.required.call(this, value2, element2, true);
        }
        return true;
      },
      /**
       * Validate that an attribute exists when all other attribute exists.
       *
       * @return {boolean}
       */
      RequiredWithAll: function(value2, element2, params2) {
        var validator = this, required = true;
        var currentObject = this;
        $.each(params2, function(i, param) {
          var target = laravelValidation.helpers.dependentElement(currentObject, element2, param);
          required = required && target !== void 0 && $.validator.methods.required.call(validator, currentObject.elementValue(target), target, true);
        });
        if (required) {
          return $.validator.methods.required.call(this, value2, element2, true);
        }
        return true;
      },
      /**
       * Validate that an attribute exists when any other attribute does not exists.
       *
       * @return {boolean}
       */
      RequiredWithout: function(value2, element2, params2) {
        var validator = this, required = false;
        var currentObject = this;
        $.each(params2, function(i, param) {
          var target = laravelValidation.helpers.dependentElement(currentObject, element2, param);
          required = required || target === void 0 || !$.validator.methods.required.call(validator, currentObject.elementValue(target), target, true);
        });
        if (required) {
          return $.validator.methods.required.call(this, value2, element2, true);
        }
        return true;
      },
      /**
       * Validate that an attribute exists when all other attribute does not exists.
       *
       * @return {boolean}
       */
      RequiredWithoutAll: function(value2, element2, params2) {
        var validator = this, required = true, currentObject = this;
        $.each(params2, function(i, param) {
          var target = laravelValidation.helpers.dependentElement(currentObject, element2, param);
          required = required && (target === void 0 || !$.validator.methods.required.call(validator, currentObject.elementValue(target), target, true));
        });
        if (required) {
          return $.validator.methods.required.call(this, value2, element2, true);
        }
        return true;
      },
      /**
       * Validate that an attribute exists when another attribute has a given value.
       *
       * @return {boolean}
       */
      RequiredIf: function(value2, element2, params2) {
        var target = laravelValidation.helpers.dependentElement(this, element2, params2[0]);
        if (target !== void 0) {
          var val = String(this.elementValue(target));
          if (typeof val !== "undefined") {
            var data = params2.slice(1);
            if ($.inArray(val, data) !== -1) {
              return $.validator.methods.required.call(this, value2, element2, true);
            }
          }
        }
        return true;
      },
      /**
       * Validate that an attribute exists when another
       * attribute does not have a given value.
       *
       * @return {boolean}
       */
      RequiredUnless: function(value2, element2, params2) {
        var target = laravelValidation.helpers.dependentElement(this, element2, params2[0]);
        if (target !== void 0) {
          var val = String(this.elementValue(target));
          if (typeof val !== "undefined") {
            var data = params2.slice(1);
            if ($.inArray(val, data) !== -1) {
              return true;
            }
          }
        }
        return $.validator.methods.required.call(this, value2, element2, true);
      },
      /**
       * Validate that an attribute has a matching confirmation.
       *
       * @return {boolean}
       */
      Confirmed: function(value2, element2, params2) {
        return laravelValidation.methods.Same.call(this, value2, element2, params2);
      },
      /**
       * Validate that two attributes match.
       *
       * @return {boolean}
       */
      Same: function(value2, element2, params2) {
        var target = laravelValidation.helpers.dependentElement(this, element2, params2[0]);
        if (target !== void 0) {
          return String(value2) === String(this.elementValue(target));
        }
        return false;
      },
      /**
       * Validate that the values of an attribute is in another attribute.
       *
       * @param value
       * @param element
       * @param params
       * @returns {boolean}
       * @constructor
       */
      InArray: function(value2, element2, params2) {
        if (typeof params2[0] === "undefined") {
          return false;
        }
        var elements = this.elements();
        var found = false;
        var nameRegExp = laravelValidation.helpers.regexFromWildcard(params2[0]);
        for (var i = 0; i < elements.length; i++) {
          var targetName = elements[i].name;
          if (targetName.match(nameRegExp)) {
            var equals = laravelValidation.methods.Same.call(this, value2, element2, [targetName]);
            found = found || equals;
          }
        }
        return found;
      },
      /**
       * Validate an attribute is unique among other values.
       *
       * @param value
       * @param element
       * @param params
       * @returns {boolean}
       */
      Distinct: function(value2, element2, params2) {
        if (typeof params2[0] === "undefined") {
          return false;
        }
        var elements = this.elements();
        var found = false;
        var nameRegExp = laravelValidation.helpers.regexFromWildcard(params2[0]);
        for (var i = 0; i < elements.length; i++) {
          var targetName = elements[i].name;
          if (targetName !== element2.name && targetName.match(nameRegExp)) {
            var equals = laravelValidation.methods.Same.call(this, value2, element2, [targetName]);
            found = found || equals;
          }
        }
        return !found;
      },
      /**
       * Validate that an attribute is different from another attribute.
       *
       * @return {boolean}
       */
      Different: function(value2, element2, params2) {
        return !laravelValidation.methods.Same.call(this, value2, element2, params2);
      },
      /**
       * Validate that an attribute was "accepted".
       * This validation rule implies the attribute is "required".
       *
       * @return {boolean}
       */
      Accepted: function(value2) {
        var regex = new RegExp("^(?:(yes|on|1|true))$", "i");
        return regex.test(value2);
      },
      /**
       * Validate that an attribute is an array.
       *
       * @param value
       * @param element
       */
      Array: function(value2, element2) {
        if (element2.name.indexOf("[") !== -1 && element2.name.indexOf("]") !== -1) {
          return true;
        }
        return Array.isArray(value2);
      },
      /**
       * Validate that an attribute is a boolean.
       *
       * @return {boolean}
       */
      Boolean: function(value2) {
        var regex = new RegExp("^(?:(true|false|1|0))$", "i");
        return regex.test(value2);
      },
      /**
       * Validate that an attribute is an integer.
       *
       * @return {boolean}
       */
      Integer: function(value2) {
        var regex = new RegExp("^(?:-?\\d+)$", "i");
        return regex.test(value2);
      },
      /**
       * Validate that an attribute is numeric.
       */
      Numeric: function(value2, element2) {
        return $.validator.methods.number.call(this, value2, element2, true);
      },
      /**
       * Validate that an attribute is a string.
       *
       * @return {boolean}
       */
      String: function(value2) {
        return typeof value2 === "string";
      },
      /**
       * The field under validation must be numeric and must have an exact length of value.
       */
      Digits: function(value2, element2, params2) {
        return $.validator.methods.number.call(this, value2, element2, true) && value2.length === parseInt(params2, 10);
      },
      /**
       * The field under validation must have a length between the given min and max.
       */
      DigitsBetween: function(value2, element2, params2) {
        return $.validator.methods.number.call(this, value2, element2, true) && value2.length >= parseFloat(params2[0]) && value2.length <= parseFloat(params2[1]);
      },
      /**
       * Validate the size of an attribute.
       *
       * @return {boolean}
       */
      Size: function(value2, element2, params2) {
        return laravelValidation.helpers.getSize(this, element2, value2) === parseFloat(params2[0]);
      },
      /**
       * Validate the size of an attribute is between a set of values.
       *
       * @return {boolean}
       */
      Between: function(value2, element2, params2) {
        return laravelValidation.helpers.getSize(this, element2, value2) >= parseFloat(params2[0]) && laravelValidation.helpers.getSize(this, element2, value2) <= parseFloat(params2[1]);
      },
      /**
       * Validate the size of an attribute is greater than a minimum value.
       *
       * @return {boolean}
       */
      Min: function(value2, element2, params2) {
        return laravelValidation.helpers.getSize(this, element2, value2) >= parseFloat(params2[0]);
      },
      /**
       * Validate the size of an attribute is less than a maximum value.
       *
       * @return {boolean}
       */
      Max: function(value2, element2, params2) {
        return laravelValidation.helpers.getSize(this, element2, value2) <= parseFloat(params2[0]);
      },
      /**
       * Validate an attribute is contained within a list of values.
       *
       * @return {boolean}
       */
      In: function(value2, element2, params2) {
        if (Array.isArray(value2) && laravelValidation.helpers.hasRules(element2, "Array")) {
          var diff = laravelValidation.helpers.arrayDiff(value2, params2);
          return Object.keys(diff).length === 0;
        }
        return params2.indexOf(value2.toString()) !== -1;
      },
      /**
       * Validate an attribute is not contained within a list of values.
       *
       * @return {boolean}
       */
      NotIn: function(value2, element2, params2) {
        return params2.indexOf(value2.toString()) === -1;
      },
      /**
       * Validate that an attribute is a valid IP.
       *
       * @return {boolean}
       */
      Ip: function(value2) {
        return /^(25[0-5]|2[0-4]\d|[01]?\d\d?)\.(25[0-5]|2[0-4]\d|[01]?\d\d?)\.(25[0-5]|2[0-4]\d|[01]?\d\d?)\.(25[0-5]|2[0-4]\d|[01]?\d\d?)$/i.test(
          value2
        ) || /^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$/i.test(
          value2
        );
      },
      /**
       * Validate that an attribute is a valid e-mail address.
       */
      Email: function(value2, element2) {
        return $.validator.methods.email.call(this, value2, element2, true);
      },
      /**
       * Validate that an attribute is a valid URL.
       */
      Url: function(value2, element2) {
        return $.validator.methods.url.call(this, value2, element2, true);
      },
      /**
       * The field under validation must be a successfully uploaded file.
       *
       * @return {boolean}
       */
      File: function(value2, element2) {
        if (!window.File || !window.FileReader || !window.FileList || !window.Blob) {
          return true;
        }
        if ("files" in element2) {
          return element2.files.length > 0;
        }
        return false;
      },
      /**
       * Validate the MIME type of a file upload attribute is in a set of MIME types.
       *
       * @return {boolean}
       */
      Mimes: function(value2, element2, params2) {
        if (!window.File || !window.FileReader || !window.FileList || !window.Blob) {
          return true;
        }
        var lowerParams = $.map(params2, function(item) {
          return item.toLowerCase();
        });
        var fileinfo = laravelValidation.helpers.fileinfo(element2);
        return fileinfo !== false && lowerParams.indexOf(fileinfo.extension.toLowerCase()) !== -1;
      },
      /**
       * The file under validation must match one of the given MIME types.
       *
       * @return {boolean}
       */
      Mimetypes: function(value2, element2, params2) {
        if (!window.File || !window.FileReader || !window.FileList || !window.Blob) {
          return true;
        }
        var lowerParams = $.map(params2, function(item) {
          return item.toLowerCase();
        });
        var fileinfo = laravelValidation.helpers.fileinfo(element2);
        if (fileinfo === false) {
          return false;
        }
        return lowerParams.indexOf(fileinfo.type.toLowerCase()) !== -1;
      },
      /**
       * Validate the MIME type of a file upload attribute is in a set of MIME types.
       */
      Image: function(value2, element2) {
        return laravelValidation.methods.Mimes.call(this, value2, element2, [
          "jpg",
          "png",
          "gif",
          "bmp",
          "svg",
          "jpeg"
        ]);
      },
      /**
       * Validate dimensions of Image.
       *
       * @return {boolean|string}
       */
      Dimensions: function(value, element, params, callback) {
        if (!window.File || !window.FileReader || !window.FileList || !window.Blob) {
          return true;
        }
        if (element.files === null || typeof element.files[0] === "undefined") {
          return false;
        }
        var fr = new FileReader();
        fr.onload = function() {
          var img = new Image();
          img.onload = function() {
            var height = parseFloat(img.naturalHeight);
            var width = parseFloat(img.naturalWidth);
            var ratio = width / height;
            var notValid = params["width"] && parseFloat(params["width"] !== width) || params["min_width"] && parseFloat(params["min_width"]) > width || params["max_width"] && parseFloat(params["max_width"]) < width || params["height"] && parseFloat(params["height"]) !== height || params["min_height"] && parseFloat(params["min_height"]) > height || params["max_height"] && parseFloat(params["max_height"]) < height || params["ratio"] && ratio !== parseFloat(eval(params["ratio"]));
            callback(!notValid);
          };
          img.onerror = function() {
            callback(false);
          };
          img.src = fr.result;
        };
        fr.readAsDataURL(element.files[0]);
        return "pending";
      },
      /**
       * Validate that an attribute contains only alphabetic characters.
       *
       * @return {boolean}
       */
      Alpha: function(value2) {
        if (typeof value2 !== "string") {
          return false;
        }
        var regex = new RegExp("^(?:^[a-zà-ü]+$)$", "i");
        return regex.test(value2);
      },
      /**
       * Validate that an attribute contains only alpha-numeric characters.
       *
       * @return {boolean}
       */
      AlphaNum: function(value2) {
        if (typeof value2 !== "string") {
          return false;
        }
        var regex = new RegExp("^(?:^[a-z0-9à-ü]+$)$", "i");
        return regex.test(value2);
      },
      /**
       * Validate that an attribute contains only alphabetic characters.
       *
       * @return {boolean}
       */
      AlphaDash: function(value2) {
        if (typeof value2 !== "string") {
          return false;
        }
        var regex = new RegExp("^(?:^[a-z0-9à-ü_-]+$)$", "i");
        return regex.test(value2);
      },
      /**
       * Validate that an attribute passes a regular expression check.
       *
       * @return {boolean}
       */
      Regex: function(value2, element2, params2) {
        var invalidModifiers = ["x", "s", "u", "X", "U", "A"];
        var phpReg = new RegExp("^(?:/)(.*\\/?[^/]*|[^/]*)(?:/)([gmixXsuUAJ]*)?$");
        var matches = params2[0].match(phpReg);
        if (matches === null) {
          return false;
        }
        var php_modifiers = [];
        if (matches[2] !== void 0) {
          php_modifiers = matches[2].split("");
          for (var i = 0; i < php_modifiers.length < i; i++) {
            if (invalidModifiers.indexOf(php_modifiers[i]) !== -1) {
              return true;
            }
          }
        }
        var regex = new RegExp("^(?:" + matches[1] + ")$", php_modifiers.join());
        return regex.test(value2);
      },
      /**
       * Validate that an attribute is a valid date.
       *
       * @return {boolean}
       */
      Date: function(value2) {
        return laravelValidation.helpers.strtotime(value2) !== false;
      },
      /**
       * Validate that an attribute matches a date format.
       *
       * @return {boolean}
       */
      DateFormat: function(value2, element2, params2) {
        return laravelValidation.helpers.parseTime(value2, params2[0]) !== false;
      },
      /**
       * Validate the date is before a given date.
       *
       * @return {boolean}
       */
      Before: function(value2, element2, params2) {
        var timeCompare = parseFloat(params2);
        if (isNaN(timeCompare)) {
          var target = laravelValidation.helpers.dependentElement(this, element2, params2);
          if (target === void 0) {
            return false;
          }
          timeCompare = laravelValidation.helpers.parseTime(this.elementValue(target), target);
        }
        var timeValue = laravelValidation.helpers.parseTime(value2, element2);
        return timeValue !== false && timeValue < timeCompare;
      },
      /**
       * Validate the date is after a given date.
       *
       * @return {boolean}
       */
      After: function(value2, element2, params2) {
        var timeCompare = parseFloat(params2);
        if (isNaN(timeCompare)) {
          var target = laravelValidation.helpers.dependentElement(this, element2, params2);
          if (target === void 0) {
            return false;
          }
          timeCompare = laravelValidation.helpers.parseTime(this.elementValue(target), target);
        }
        var timeValue = laravelValidation.helpers.parseTime(value2, element2);
        return timeValue !== false && timeValue > timeCompare;
      },
      /**
       * Validate that an attribute is a valid date.
       */
      Timezone: function(value2) {
        return laravelValidation.helpers.isTimezone(value2);
      },
      /**
       * Validate the attribute is a valid JSON string.
       *
       * @param  value
       * @return bool
       */
      Json: function(value2) {
        var result = true;
        try {
          JSON.parse(value2);
        } catch (e) {
          result = false;
        }
        return result;
      }
    }
  });
})();
