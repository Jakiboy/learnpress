/**
 * Single Quiz functions
 *
 * @author ThimPress
 * @package LearnPress/JS
 * @version 1.1
 */
;(function ($) {

	var Lesson = function (args) {
		this.model = new Lesson.Model(args);
		this.view = new Lesson.View({
			model: this.model
		});
	}, windowTarget = parent.window || window;

	Lesson.Model = Backbone.Model.extend({

	});
	Lesson.View = Backbone.View.extend({
		el                    : function () {
			return 'body';
		},
		events                : {
			'click .button-complete-item'               : '_completeItem'
		},
		_completeItem      : function (e) {
			var that = this,
				$button = $(e.target),
				security = $button.data('security'),
				$item = $button.closest('.course-item');
			windowTarget.LP.blockContent();
			return;
			this.complete({
				security  : security,
				course_id : this.model.get('courseId'),
				callback  : function (response, item) {
					if (response.result == 'success') {
						// highlight item
						item.$el.removeClass('item-started').addClass('item-completed focus off');
						// then restore back after 3 seconds
						_.delay(function (item) {
							item.$el.removeClass('focus off');
						}, 3000, item);

						/*that.$('.learn-press-course-results-progress').replaceWith($(response.html.progress));
						$section.find('.section-header').replaceWith($(response.html.section_header));
						that.$('.learn-press-course-buttons').replaceWith($(response.html.buttons));
						that.currentItem.set('content', $(response.html.content))*/
						windowTarget.LP.setUrl(that.model.get('permalink'));
						var data = response.course_result;
						data.messageType = 'update-course';
						LP.sendMessage(data, windowTarget);
					}
					windowTarget.LP.unblockContent();
				}
			});
		},
		complete       : function (args) {
			var that = this;
			args = $.extend({
				context : null,
				callback: null,
				format  : 'json'
			}, this.model.toJSON(), args || {});
			var data = {};

			// Omit unwanted fields
			_.forEach(args, function (v, k) {
				if (($.inArray(k, ['content', 'current', 'title', 'url']) == -1) && !$.isFunction(v)) {
					data[k] = v;
				}
				;
			});
			LP.ajax({
				url     : this.model.get('url'),
				action  : 'complete-item',
				data    : data,
				dataType: 'json',
				success : function (response) {
					///response = LP.parseJSON(response);
					LP.Hook.doAction('learn_press_course_item_completed', response, that);
					response = LP.Hook.applyFilters('learn_press_course_item_complete_response', response, that);
					$.isFunction(args.callback) && args.callback.call(args.context, response, that);
				}
			});
		},
		_validateObject: function (obj) {
			var ret = {};
			for (var i in obj) {
				if (!$.isFunction(obj[i])) {
					ret[i] = obj[i];
				}
			}
			return ret;
		}
	});

	window.LP_Lesson = Lesson;
	$(document).ready(function () {
		if (typeof Lesson_Params != 'undefined') {
			window.lesson = new LP_Lesson($.extend({course: LP.$Course}, Lesson_Params));
		}
		windowTarget.LP.unblockContent();
	})
	// DOM ready
	LP.Hook.addAction('learn_press_course_initialize', function ($course) {
		if (typeof Quiz_Params != 'undefined') {
			//window.quiz = new LP_Quiz($.extend({course: $course}, Quiz_Params));
			$course.view.updateUrl();
		}
	});

})(jQuery);