# -*- coding: utf-8 -*-
import os
import time
import hashlib
from flask import Flask, flash, request, redirect, url_for, send_from_directory, jsonify, render_template
from flask_script import Manager
from flask_wtf import FlaskForm
from wtforms import StringField, SubmitField, FileField
from flask_wtf.file import FileField, FileRequired, FileAllowed
from flask_migrate import Migrate, MigrateCommand
from werkzeug.utils import secure_filename
from flask_sqlalchemy import SQLAlchemy
from pypinyin import lazy_pinyin
from flask_uploads import UploadSet, configure_uploads, patch_request_class, WEBSHELL
from sklearn.feature_extraction.text import CountVectorizer, TfidfTransformer
from sklearn.neural_network import MLPClassifier
from sklearn.naive_bayes import GaussianNB
from sklearn import metrics, svm
from sklearn.model_selection import train_test_split
from sklearn.externals import joblib
import numpy as np
import pickle


PKL_DIR = os.getcwd() + "/pkl_objects/"

cv_pkl = PKL_DIR + "cv.pkl"
mlp_pkl = PKL_DIR + "mlp.pkl"
transformer_pkl = PKL_DIR + "transformer.pkl"

app = Flask(__name__)
app.config['SECRET_KEY'] = 'Th1s_is_SeCret'
app.config['UPLOADED_PHOTOS_DEST'] = os.getcwd() + '/static/'
###
#The setup of the flask.
###

photos = UploadSet('photos', WEBSHELL)
configure_uploads(app, photos)
patch_request_class(app)  # set maximum file size, default is 16MB


class UploadForm(FlaskForm):
    '''
    This class define the form of uploading.
    '''
    photo = FileField(validators=[FileAllowed(photos, u'WebShell Only!'), FileRequired(u'Choose a file!')])
    submit = SubmitField(u'Upload')


def load_file(file_path):
    t = b''
    with open(file_path, "rb") as f:
        for line in f:
            line = line.strip(b'\r\n')
            t += line
    return t
#%%
###
#This function is used to load file of a new file.
###

def check(clf, cv, transformer, path, filename):
    file_full_path = path + filename
    t = load_file(file_full_path)
    t_list = list()
    t_list.append(t)
    x = cv.transform(t_list).toarray()
    x = transformer.transform(x).toarray()
    y_pred = clf.predict(x)
    if y_pred[0] == 1:
        return True
    else:
        return False
###
#Th1s function is used to judge if the file is a webshell.
###

@app.route('/', methods=['GET', 'POST'])
def upload_file():
    form = UploadForm()
    websh = True
    if form.validate_on_submit():
        for filename in request.files.getlist('photo'):
            name = hashlib.md5(('websHell' + str(time.time())).encode('UTF-8')).hexdigest()[:15]
            finame = photos.save(filename, name=name + '.')
            if check(joblib.load(mlp_pkl), joblib.load(cv_pkl), \
                     joblib.load(transformer_pkl), \
                      app.config['UPLOADED_PHOTOS_DEST'], finame):
                websh = True
            else:
                websh = False
        success = True
    else:
        success = False
    return render_template('index.html', form=form, success=success, websh=websh)
###
#This route function handles the upload conditons.
###

@app.route('/manage')
def manage_file():
    files_list = os.listdir(app.config['UPLOADED_PHOTOS_DEST'])
    return render_template('manage.html', files_list=files_list)


@app.route('/open/<filename>')
def open_file(filename):
    file_url = photos.url(filename)
    return render_template('browser.html', file_url=file_url)


@app.route('/delete/<filename>')
def delete_file(filename):
    file_path = photos.path(filename)
    os.remove(file_path)
    return redirect(url_for('manage_file'))

@app.errorhandler(404)
def page_not_found(e):
    return render_template("404.html", title='Not Found'),404


@app.errorhandler(500)
def internal_server_error(e):
    return render_template('500.html', title="Internal Error"), 500
###
#The two functions above handle the server expections.
###

if __name__ == '__main__':
    app.run(debug=True,port='8900')
